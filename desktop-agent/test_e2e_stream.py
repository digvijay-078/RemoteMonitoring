"""
RemoteMonitor — End-to-End Real WebRTC Screen & Audio Streaming Verification.
Tests the full live pipeline:
  Windows Desktop Agent (Capturing 1920x1080 Screen + WASAPI Audio)
  ↕ WebRTC (aiortc)
  Laravel Reverb Signalling Control Plane
  ↕ WebSocket (Pusher v7)
  Tablet WebRTC Receiver Client
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
    RTCIceCandidate
)

FLEET_CONFIG_PATH = r"C:\RemoteMonitoring\storage\app\demo_fleet.json"


def load_fleet_config():
    if not os.path.exists(FLEET_CONFIG_PATH):
        raise RuntimeError(f"Demo fleet configuration not found at {FLEET_CONFIG_PATH}. Run 'php artisan stream:provision' first.")
    with open(FLEET_CONFIG_PATH, "r") as f:
        return json.load(f)


async def run_e2e_stream_test():
    print("=" * 75)
    print("  REMOTEMONITOR — REAL WEBRTC SCREEN & SYSTEM AUDIO E2E VERIFICATION")
    print("=" * 75)

    fleet = load_fleet_config()
    server_url = fleet.get("server_url", "http://127.0.0.1:8000")
    ws_url = fleet.get("ws_url", "ws://127.0.0.1:8080")
    app_key = fleet.get("app_key", "nw2zhrpowiazy7xm9esc")

    tablet1 = fleet["tablet1"]
    desktop1 = fleet["desktop1"]

    tablet_token = tablet1["token"]
    tablet_uuid = tablet1["uuid"]
    tablet_id = tablet1["identifier"]

    desktop_uuid = desktop1["uuid"]
    desktop_id = desktop1["identifier"]

    headers = {
        "Authorization": f"Bearer {tablet_token}",
        "Content-Type": "application/json",
        "Accept": "application/json",
    }

    # Step 1: Verify Tablet Authentication & Assigned Desktops Fleet
    print(f"\n[E2E Step 1] Authenticating Tablet {tablet_id} (UUID: {tablet_uuid})...")
    desk_res = requests.get(f"{server_url}/api/v1/tablet/desktops", headers=headers, timeout=10)
    assert desk_res.status_code == 200, f"Failed to fetch assigned desktops: {desk_res.text}"
    desk_data = desk_res.json()
    assigned_desktops = desk_data.get("desktops", [])
    print(f"  [OK] Tablet Authenticated. Assigned Desktops count: {len(assigned_desktops)}")
    for d in assigned_desktops:
        print(f"       - Desktop: {d['device_identifier']} ({d['name']}) [Status: {d['status']}, Stream: {d['stream_status']}]")

    target_desktop = next((d for d in assigned_desktops if d["uuid"] == desktop_uuid), None)
    assert target_desktop is not None, f"Desktop {desktop_id} not assigned to {tablet_id}!"
    print(f"  [OK] Mapping Verified: Tablet {tablet_id} is authorized to stream {desktop_id}")

    # Step 2: Connect Tablet to Laravel Reverb Signalling Plane
    print(f"\n[E2E Step 2] Connecting Tablet to Reverb WebSocket at {ws_url} ...")
    ws_uri = f"{ws_url}/app/{app_key}?protocol=7&client=py-tablet-e2e&version=1.0.0&flash=false"
    
    received_offer_future = asyncio.get_running_loop().create_future()
    ice_candidates_received = []

    async with websockets.connect(ws_uri, ping_interval=30, ping_timeout=10) as ws:
        # Pusher handshake
        msg = await ws.recv()
        data = json.loads(msg)
        assert data.get("event") == "pusher:connection_established", f"Unexpected handshake frame: {msg}"
        socket_id = json.loads(data["data"])["socket_id"]
        print(f"  [OK] Reverb WebSocket Connected. Handshake Socket ID: {socket_id}")

        # Authorize private channel
        auth_res = requests.post(f"{server_url}/api/v1/device/broadcasting/auth", json={
            "socket_id": socket_id,
            "channel_name": f"private-device.{tablet_uuid}"
        }, headers=headers, timeout=10)
        assert auth_res.status_code == 200, f"Channel auth failed: {auth_res.text}"
        auth_signature = auth_res.json()["auth"]

        # Subscribe to private-device.{uuid}
        await ws.send(json.dumps({
            "event": "pusher:subscribe",
            "data": {
                "channel": f"private-device.{tablet_uuid}",
                "auth": auth_signature
            }
        }))
        sub_confirm = await ws.recv()
        print(f"  [OK] Subscribed to private channel: private-device.{tablet_uuid}")

        # Step 3: Initiate WebRTC Session via Control Plane
        print(f"\n[E2E Step 3] Initiating WebRTC streaming session with Desktop {desktop_id} ...")
        init_res = requests.post(f"{server_url}/api/v1/webrtc/session/initiate", json={
            "desktop_uuid": desktop_uuid
        }, headers=headers, timeout=10)
        assert init_res.status_code == 200, f"Session initiate error: {init_res.text}"
        init_data = init_res.json()
        session_id = init_data["session_id"]
        ice_servers_config = init_data.get("ice_servers", [{"urls": "stun:stun.l.google.com:19302"}])
        print(f"  [OK] WebRTC Session Created: {session_id}")
        print(f"  [OK] Configured ICE Servers: {[s.get('urls') for s in ice_servers_config]}")

        # Start Reverb event listener
        async def listen_reverb_events():
            try:
                async for frame in ws:
                    f_data = json.loads(frame)
                    ev = f_data.get("event", "").lstrip(".")
                    if ev in ["webrtc.signal.offer", "WebRtcOfferReceived"]:
                        payload = json.loads(f_data["data"]) if isinstance(f_data["data"], str) else f_data["data"]
                        if not received_offer_future.done():
                            received_offer_future.set_result(payload)
                    elif ev in ["webrtc.signal.ice_candidate", "WebRtcIceCandidateReceived"]:
                        payload = json.loads(f_data["data"]) if isinstance(f_data["data"], str) else f_data["data"]
                        ice_candidates_received.append(payload)
                    elif ev == "pusher:ping":
                        await ws.send(json.dumps({"event": "pusher:pong", "data": {}}))
            except asyncio.CancelledError:
                pass

        listener_task = asyncio.create_task(listen_reverb_events())

        # Step 4: Wait for Desktop Agent to send SDP Offer
        print(f"\n[E2E Step 4] Waiting for Windows Desktop Agent to send SDP Offer for session {session_id} ...")
        try:
            offer_payload = await asyncio.wait_for(received_offer_future, timeout=20.0)
            print("  [OK] Received SDP Offer from Windows Desktop Agent via Reverb!")
        except asyncio.TimeoutError:
            print("  [FAIL] Timed out waiting for SDP Offer from Desktop Agent.")
            print("         Ensure Desktop Agent is running ('python agent.py start').")
            listener_task.cancel()
            return False

        # Step 5: Tablet Creates Local PeerConnection & Relays SDP Answer
        print(f"\n[E2E Step 5] Tablet creating RTCPeerConnection and responding with SDP Answer...")
        rtc_servers = []
        for s in ice_servers_config:
            urls = s.get("urls")
            if urls:
                rtc_servers.append(RTCIceServer(urls=urls, username=s.get("username"), credential=s.get("credential")))
        pc = RTCPeerConnection(RTCConfiguration(iceServers=rtc_servers if rtc_servers else [RTCIceServer("stun:stun.l.google.com:19302")]))

        video_frames = []
        audio_frames = []

        @pc.on("track")
        def on_track(track):
            print(f"  [MEDIA TRACK RECEIVED] WebRTC MediaStreamTrack arriving: kind='{track.kind}'")
            if track.kind == "video":
                asyncio.create_task(_consume_video(track, video_frames))
            elif track.kind == "audio":
                asyncio.create_task(_consume_audio(track, audio_frames))

        async def _consume_video(track, storage):
            while len(storage) < 30:
                try:
                    vf = await track.recv()
                    storage.append(vf)
                except Exception:
                    break

        async def _consume_audio(track, storage):
            while len(storage) < 30:
                try:
                    af = await track.recv()
                    storage.append(af)
                except Exception:
                    break

        # Apply remote offer
        offer_sdp = offer_payload["sdp"]
        await pc.setRemoteDescription(RTCSessionDescription(type=offer_sdp["type"], sdp=offer_sdp["sdp"]))

        # Generate answer
        answer = await pc.createAnswer()
        await pc.setLocalDescription(answer)

        # Relay answer to server
        ans_res = requests.post(f"{server_url}/api/v1/webrtc/signal/answer", json={
            "session_id": session_id,
            "sdp": {
                "type": answer.type,
                "sdp": answer.sdp
            }
        }, headers=headers, timeout=10)
        assert ans_res.status_code == 200, f"Answer relay failed: {ans_res.text}"
        print("  [OK] SDP Answer Relayed to Desktop Agent via Laravel/Reverb.")

        # Step 6: Wait and Verify Real Video and Audio Media Frames
        print(f"\n[E2E Step 6] Streaming media over WebRTC: capturing live frames...")
        t0 = time.time()
        while (len(video_frames) < 15 or len(audio_frames) < 15) and (time.time() - t0 < 15.0):
            await asyncio.sleep(0.5)

        print(f"  [OK] Captured {len(video_frames)} live Screen Video frames over WebRTC!")
        print(f"  [OK] Captured {len(audio_frames)} live System Audio frames over WebRTC!")

        assert len(video_frames) >= 5, "Failed to receive video frames over WebRTC!"
        assert len(audio_frames) >= 5, "Failed to receive audio frames over WebRTC!"

        # Verify Video Frame Properties
        sample_vf = video_frames[0]
        vf_arr = sample_vf.to_ndarray(format="bgr24")
        std_dev = float(np.std(vf_arr))
        print(f"\n  [VERIFICATION - VIDEO SCREEN]:")
        print(f"    - Resolution : {sample_vf.width}x{sample_vf.height} (Standard Desktop Resolution)")
        print(f"    - Format     : {sample_vf.format.name}")
        print(f"    - PTS        : {sample_vf.pts} (Timebase: {sample_vf.time_base})")
        print(f"    - Pixel Var  : StdDev = {std_dev:.2f} (Non-blank actual desktop pixels)")
        assert sample_vf.width == 1920 and sample_vf.height == 1080, f"Unexpected resolution: {sample_vf.width}x{sample_vf.height}"
        assert std_dev > 5.0, "Video frame appears to be blank/flat!"

        # Verify Audio Frame Properties
        sample_af = audio_frames[0]
        af_arr = sample_af.to_ndarray()
        print(f"\n  [VERIFICATION - SYSTEM AUDIO]:")
        print(f"    - Sample Rate: {sample_af.sample_rate}Hz (Standard Opus clock)")
        print(f"    - Samples    : {sample_af.samples} (20ms per frame)")
        print(f"    - Layout     : {sample_af.layout.name}")
        print(f"    - Shape      : {af_arr.shape}")
        assert sample_af.sample_rate == 48000, f"Unexpected sample rate: {sample_af.sample_rate}"
        assert sample_af.samples == 960, f"Unexpected frame sample count: {sample_af.samples}"

        # Step 7: Update Session Status and Verify Active Viewer Tracking
        print(f"\n[E2E Step 7] Notifying server of 'connected' status...")
        conn_res = requests.post(f"{server_url}/api/v1/webrtc/session/status", json={
            "session_id": session_id,
            "status": "connected"
        }, headers=headers, timeout=10)
        assert conn_res.status_code == 200, f"Status update failed: {conn_res.text}"
        conn_data = conn_res.json()
        print(f"  [OK] Server State: active_viewers={conn_data['active_viewers_count']}, stream_status='{conn_data['stream_status']}'")
        assert conn_data["active_viewers_count"] >= 1, "Active viewers count should be >= 1"
        assert conn_data["stream_status"] == "streaming", "Stream status should be 'streaming'"

        # Step 8: Clean Session Termination
        print(f"\n[E2E Step 8] Terminating WebRTC session cleanly...")
        term_res = requests.post(f"{server_url}/api/v1/webrtc/session/status", json={
            "session_id": session_id,
            "status": "terminated",
            "reason": "E2E verification completed successfully"
        }, headers=headers, timeout=10)
        assert term_res.status_code == 200
        term_data = term_res.json()
        print(f"  [OK] Terminated State: active_viewers={term_data['active_viewers_count']}, stream_status='{term_data['stream_status']}'")
        assert term_data["active_viewers_count"] == 0
        assert term_data["stream_status"] == "idle"

        await pc.close()
        listener_task.cancel()

    print("\n" + "=" * 75)
    print("  E2E STREAM VERIFICATION 100% SUCCESSFUL — REAL SCREEN + AUDIO STREAMED!")
    print("=" * 75)
    return True


if __name__ == "__main__":
    success = asyncio.run(run_e2e_stream_test())
    sys.exit(0 if success else 1)
