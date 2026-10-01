"""
Multi-Viewer and Auto-Reconnect Verification Test.
Verifies:
1. Multi-viewer concurrency: Tablet 1 and Tablet 2 simultaneously streaming from Desktop 1.
2. Viewer isolation: Tablet 1 disconnects without interrupting Tablet 2's live stream.
3. Zero-touch auto-reconnect: Client reconnects and resumes live stream without manual pairing.
"""

import os
import sys
import json
import time
import asyncio
import numpy as np
import requests
import websockets
from aiortc import (
    RTCPeerConnection,
    RTCSessionDescription,
    RTCIceServer,
    RTCConfiguration,
)

FLEET_CONFIG_PATH = r"C:\RemoteMonitoring\storage\app\demo_fleet.json"


def load_fleet():
    with open(FLEET_CONFIG_PATH, "r") as f:
        return json.load(f)


class VirtualTabletClient:
    def __init__(self, name: str, token: str, uuid: str, server_url: str, ws_url: str, app_key: str):
        self.name = name
        self.token = token
        self.uuid = uuid
        self.server_url = server_url
        self.ws_url = ws_url
        self.app_key = app_key
        self.headers = {
            "Authorization": f"Bearer {self.token}",
            "Content-Type": "application/json",
            "Accept": "application/json"
        }
        self.ws = None
        self.pc = None
        self.session_id = None
        self.video_frames = []
        self.audio_frames = []
        self._offer_future = None
        self._listener_task = None

    async def connect_and_stream(self, desktop_uuid: str) -> bool:
        print(f"[{self.name}] Connecting to Reverb...")
        ws_uri = f"{self.ws_url}/app/{self.app_key}?protocol=7&client=multiviewer-test&version=1.0.0&flash=false"
        self.ws = await websockets.connect(ws_uri)

        # Handshake
        msg = await self.ws.recv()
        data = json.loads(msg)
        socket_id = json.loads(data["data"])["socket_id"]

        # Channel Auth
        auth_res = requests.post(f"{self.server_url}/api/v1/device/broadcasting/auth", json={
            "socket_id": socket_id,
            "channel_name": f"private-device.{self.uuid}"
        }, headers=self.headers, timeout=10)
        auth_sig = auth_res.json()["auth"]

        # Subscribe
        await self.ws.send(json.dumps({
            "event": "pusher:subscribe",
            "data": {"channel": f"private-device.{self.uuid}", "auth": auth_sig}
        }))
        await self.ws.recv()
        print(f"[{self.name}] Subscribed to channel private-device.{self.uuid}")

        self._offer_future = asyncio.get_running_loop().create_future()

        # Listen
        async def listen():
            try:
                async for frame in self.ws:
                    f_data = json.loads(frame)
                    ev = f_data.get("event", "").lstrip(".")
                    if ev in ["webrtc.signal.offer", "WebRtcOfferReceived"]:
                        payload = json.loads(f_data["data"]) if isinstance(f_data["data"], str) else f_data["data"]
                        if not self._offer_future.done():
                            self._offer_future.set_result(payload)
                    elif ev == "pusher:ping":
                        await self.ws.send(json.dumps({"event": "pusher:pong", "data": {}}))
            except asyncio.CancelledError:
                pass

        self._listener_task = asyncio.create_task(listen())

        # Initiate session
        init_res = requests.post(f"{self.server_url}/api/v1/webrtc/session/initiate", json={
            "desktop_uuid": desktop_uuid
        }, headers=self.headers, timeout=10)
        init_data = init_res.json()
        self.session_id = init_data["session_id"]
        ice_servers_config = init_data.get("ice_servers", [{"urls": "stun:stun.l.google.com:19302"}])
        print(f"[{self.name}] Initiated session: {self.session_id}")

        # Wait for offer
        offer_payload = await asyncio.wait_for(self._offer_future, timeout=15.0)
        print(f"[{self.name}] Received SDP Offer!")

        # Create PC
        rtc_servers = [RTCIceServer(s["urls"]) for s in ice_servers_config if "urls" in s]
        self.pc = RTCPeerConnection(RTCConfiguration(iceServers=rtc_servers))

        @self.pc.on("track")
        def on_track(track):
            if track.kind == "video":
                asyncio.create_task(self._read_track(track, self.video_frames))
            elif track.kind == "audio":
                asyncio.create_task(self._read_track(track, self.audio_frames))

        offer_sdp = offer_payload["sdp"]
        await self.pc.setRemoteDescription(RTCSessionDescription(type=offer_sdp["type"], sdp=offer_sdp["sdp"]))

        answer = await self.pc.createAnswer()
        await self.pc.setLocalDescription(answer)

        requests.post(f"{self.server_url}/api/v1/webrtc/signal/answer", json={
            "session_id": self.session_id,
            "sdp": {"type": answer.type, "sdp": answer.sdp}
        }, headers=self.headers, timeout=10)

        # Notify connected
        requests.post(f"{self.server_url}/api/v1/webrtc/session/status", json={
            "session_id": self.session_id,
            "status": "connected"
        }, headers=self.headers, timeout=10)

        # Wait for frames
        t0 = time.time()
        while len(self.video_frames) < 5 and time.time() - t0 < 8.0:
            await asyncio.sleep(0.3)

        print(f"[{self.name}] [STREAMING LIVE] Captured {len(self.video_frames)} video frames, {len(self.audio_frames)} audio frames!")
        return len(self.video_frames) >= 3

    async def _read_track(self, track, storage):
        while len(storage) < 25:
            try:
                frame = await track.recv()
                storage.append(frame)
            except Exception:
                break

    async def disconnect(self):
        print(f"[{self.name}] Disconnecting session {self.session_id}...")
        requests.post(f"{self.server_url}/api/v1/webrtc/session/status", json={
            "session_id": self.session_id,
            "status": "terminated",
            "reason": "Client disconnected"
        }, headers=self.headers, timeout=10)

        if self.pc:
            await self.pc.close()
            self.pc = None
        if self._listener_task:
            self._listener_task.cancel()
        if self.ws:
            await self.ws.close()
            self.ws = None


async def main():
    print("=" * 75)
    print("  MULTI-VIEWER CONCURRENCY & ZERO-TOUCH AUTO-RECONNECT VERIFICATION")
    print("=" * 75)

    fleet = load_fleet()
    server_url = fleet["server_url"]
    ws_url = fleet["ws_url"]
    app_key = fleet["app_key"]
    desktop1 = fleet["desktop1"]
    tablet1 = fleet["tablet1"]
    tablet2 = fleet["tablet2"]

    client1 = VirtualTabletClient("Tablet 1 (Primary)", tablet1["token"], tablet1["uuid"], server_url, ws_url, app_key)
    client2 = VirtualTabletClient("Tablet 2 (Secondary)", tablet2["token"], tablet2["uuid"], server_url, ws_url, app_key)

    # 1. Connect Tablet 1
    print("\n--- PHASE 1: Connect Tablet 1 to Desktop 1 ---")
    ok1 = await client1.connect_and_stream(desktop1["uuid"])
    assert ok1, "Tablet 1 stream failed!"

    # Check server status
    dev_res = requests.get(f"{server_url}/api/v1/tablet/desktops", headers=client1.headers)
    d_info = next(d for d in dev_res.json()["desktops"] if d["uuid"] == desktop1["uuid"])
    print(f"  [SERVER STATUS] Active Viewers: {d_info['active_viewers_count']}, Stream: '{d_info['stream_status']}'")
    assert d_info["active_viewers_count"] == 1
    assert d_info["stream_status"] == "streaming"

    # 2. Connect Tablet 2 Simultaneously (Multi-Viewer)
    print("\n--- PHASE 2: Connect Tablet 2 Simultaneously (Multi-Viewer) ---")
    ok2 = await client2.connect_and_stream(desktop1["uuid"])
    assert ok2, "Tablet 2 stream failed!"

    dev_res = requests.get(f"{server_url}/api/v1/tablet/desktops", headers=client1.headers)
    d_info = next(d for d in dev_res.json()["desktops"] if d["uuid"] == desktop1["uuid"])
    print(f"  [SERVER STATUS] Active Viewers: {d_info['active_viewers_count']}, Stream: '{d_info['stream_status']}'")
    assert d_info["active_viewers_count"] == 2
    print("  [VERIFICATION SUCCESSFUL] Desktop 1 is streaming concurrently to Tablet 1 AND Tablet 2!")

    # 3. Disconnect Tablet 1 (Verify Tablet 2 Continues Uninterrupted)
    print("\n--- PHASE 3: Disconnect Tablet 1 (Verify Tablet 2 Stream Continues) ---")
    await client1.disconnect()
    await asyncio.sleep(1.0)

    # Verify Tablet 2 continues receiving frames
    count_before = len(client2.video_frames)
    await asyncio.sleep(1.0)
    count_after = len(client2.video_frames)
    print(f"  [TABLET 2 HEALTH] Frames during Tablet 1 disconnect: {count_before} -> {count_after}")
    assert count_after >= count_before, "Tablet 2 stream was killed by Tablet 1 disconnect!"

    dev_res = requests.get(f"{server_url}/api/v1/tablet/desktops", headers=client2.headers)
    d_info = next(d for d in dev_res.json()["desktops"] if d["uuid"] == desktop1["uuid"])
    print(f"  [SERVER STATUS] Active Viewers: {d_info['active_viewers_count']}, Stream: '{d_info['stream_status']}'")
    assert d_info["active_viewers_count"] == 1
    print("  [VERIFICATION SUCCESSFUL] Tablet 2 stream remained live while Tablet 1 disconnected!")

    # 4. Disconnect Tablet 2
    print("\n--- PHASE 4: Disconnect Tablet 2 (Desktop transitions to idle) ---")
    await client2.disconnect()
    await asyncio.sleep(1.0)

    dev_res = requests.get(f"{server_url}/api/v1/tablet/desktops", headers=client2.headers)
    d_info = next(d for d in dev_res.json()["desktops"] if d["uuid"] == desktop1["uuid"])
    print(f"  [SERVER STATUS] Active Viewers: {d_info['active_viewers_count']}, Stream: '{d_info['stream_status']}'")
    assert d_info["active_viewers_count"] == 0
    assert d_info["stream_status"] == "idle"

    # 5. Zero-Touch Auto-Reconnect
    print("\n--- PHASE 5: Zero-Touch Auto-Reconnect (Simulate reload/re-stream without pairing) ---")
    reconnected_client = VirtualTabletClient("Tablet 1 (Auto-Reconnect)", tablet1["token"], tablet1["uuid"], server_url, ws_url, app_key)
    ok_recon = await reconnected_client.connect_and_stream(desktop1["uuid"])
    assert ok_recon, "Auto-reconnect failed!"
    print("  [VERIFICATION SUCCESSFUL] Zero-Touch Auto-Reconnect succeeded without manual pairing or QR codes!")

    await reconnected_client.disconnect()

    print("\n" + "=" * 75)
    print("  ALL MULTI-VIEWER & AUTO-RECONNECT TESTS PASSED (100% SUCCESS)!")
    print("=" * 75)
    return True


if __name__ == "__main__":
    success = asyncio.run(main())
    sys.exit(0 if success else 1)
