"""
Laravel Reverb WebSocket Signalling Client for Windows Desktop Agent.
Maintains persistent WebSocket connection to Reverb control plane,
subscribes to private-device.{uuid} with Bearer token authentication,
and routes WebRTC signalling events.
"""

import asyncio
import json
import time
import requests
import websockets


class ReverbSignallingClient:
    """Async WebSocket client connecting Desktop Agent to Laravel Reverb."""

    def __init__(self, server_url: str, ws_url: str, app_key: str, device_uuid: str, device_token: str, on_event_callback):
        self.server_url = server_url.rstrip("/")
        import urllib.parse
        if self.server_url.startswith("https://"):
            parsed = urllib.parse.urlparse(self.server_url)
            self.ws_url = f"wss://{parsed.hostname}"
        elif ws_url:
            cleaned_ws = ws_url.rstrip("/")
            if cleaned_ws.startswith("http://"):
                cleaned_ws = "ws://" + cleaned_ws[7:]
            elif cleaned_ws.startswith("https://"):
                cleaned_ws = "wss://" + cleaned_ws[8:]
            self.ws_url = cleaned_ws
        else:
            parsed = urllib.parse.urlparse(self.server_url)
            self.ws_url = f"ws://{parsed.hostname or '127.0.0.1'}:8080"
        self.app_key = app_key
        self.device_uuid = device_uuid
        self.device_token = device_token
        self.on_event_callback = on_event_callback
        self.channel_name = f"private-device.{device_uuid}"
        
        self.ws = None
        self.socket_id = None
        self._is_running = True
        self._heartbeat_task = None

    async def run(self):
        """Main connection loop with auto-reconnect."""
        backoff = 1.0
        while self._is_running:
            try:
                ws_uri = f"{self.ws_url}/app/{self.app_key}?protocol=7&client=py-agent&version=1.0.0&flash=false"
                print(f"[Signalling] Connecting to Reverb at {ws_uri} ...")

                async with websockets.connect(ws_uri, ping_interval=30, ping_timeout=10) as ws:
                    self.ws = ws
                    backoff = 1.0
                    print("[Signalling] WebSocket connected to Reverb.")

                    # Start periodic HTTP heartbeat task
                    self._heartbeat_task = asyncio.create_task(self._heartbeat_loop())

                    # Process incoming WebSocket frames
                    async for message in ws:
                        await self._handle_message(message)

            except (websockets.ConnectionClosed, OSError, Exception) as e:
                print(f"[Signalling] WebSocket connection lost ({e}). Reconnecting in {backoff:.1f}s...")
                if self._heartbeat_task:
                    self._heartbeat_task.cancel()
                await asyncio.sleep(backoff)
                backoff = min(backoff * 1.5, 30.0)

    async def _handle_message(self, message_raw: str):
        try:
            data = json.loads(message_raw)
            event = data.get("event", "")

            if event == "pusher:connection_established":
                conn_data = json.loads(data.get("data", "{}"))
                self.socket_id = conn_data.get("socket_id")
                print(f"[Signalling] Connection established. Socket ID: {self.socket_id}")
                await self._subscribe_channel()

            elif event == "pusher:ping":
                if self.ws:
                    await self.ws.send(json.dumps({"event": "pusher:pong", "data": {}}))

            elif event == "pusher_internal:subscription_succeeded":
                print(f"[Signalling] Successfully subscribed to {self.channel_name}")

            clean_event = event.lstrip(".")
            if "WebRtcSessionRequested" in clean_event or clean_event == "webrtc.session.requested":
                clean_event = "webrtc.session.requested"
            elif "WebRtcAnswerReceived" in clean_event or clean_event == "webrtc.signal.answer":
                clean_event = "webrtc.signal.answer"
            elif "WebRtcIceCandidateReceived" in clean_event or clean_event == "webrtc.signal.ice_candidate":
                clean_event = "webrtc.signal.ice_candidate"
            elif "WebRtcSessionStatusChanged" in clean_event or clean_event == "webrtc.session.status":
                clean_event = "webrtc.session.status"

            if clean_event.startswith("webrtc."):
                payload = data.get("data", {})
                if isinstance(payload, str):
                    try:
                        payload = json.loads(payload)
                    except Exception:
                        pass
                
                # Dispatch event to streamer manager
                if self.on_event_callback:
                    asyncio.create_task(self.on_event_callback(clean_event, payload))

        except Exception as e:
            print(f"[Signalling] Error handling message: {e}")

    async def _subscribe_channel(self):
        """Authorize with Laravel and subscribe to private device channel."""
        auth_url = f"{self.server_url}/api/v1/device/broadcasting/auth"
        headers = {
            "Authorization": f"Bearer {self.device_token}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        body = {
            "socket_id": self.socket_id,
            "channel_name": self.channel_name,
        }

        loop = asyncio.get_running_loop()
        try:
            res = await loop.run_in_executor(None, lambda: requests.post(auth_url, json=body, headers=headers, timeout=10))
            if res.status_code != 200:
                print(f"[Signalling] Channel auth failed (HTTP {res.status_code}): {res.text}")
                return

            auth_info = res.json()
            auth_signature = auth_info.get("auth")

            # Send subscription message over WebSocket
            sub_msg = {
                "event": "pusher:subscribe",
                "data": {
                    "channel": self.channel_name,
                    "auth": auth_signature,
                }
            }
            if self.ws:
                await self.ws.send(json.dumps(sub_msg))
                print(f"[Signalling] Sent subscription request for {self.channel_name}")

        except Exception as e:
            print(f"[Signalling] Error during channel subscription auth: {e}")

    async def _heartbeat_loop(self):
        """Send periodic heartbeat to server (initial instant heartbeat, then every 3 seconds)."""
        while self._is_running:
            try:
                loop = asyncio.get_running_loop()
                hb_url = f"{self.server_url}/api/v1/device/heartbeat"
                headers = {
                    "Authorization": f"Bearer {self.device_token}",
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "ngrok-skip-browser-warning": "1",
                    "User-Agent": "RemoteMonitorAgent/2.0",
                }
                await loop.run_in_executor(None, lambda: requests.post(hb_url, json={"timestamp": int(time.time()), "token": self.device_token}, headers=headers, timeout=5))
                await asyncio.sleep(3)
            except asyncio.CancelledError:
                break
            except Exception:
                await asyncio.sleep(3)

    def stop(self):
        self._is_running = False
        try:
            dc_url = f"{self.server_url}/api/v1/device/disconnect"
            headers = {
                "Authorization": f"Bearer {self.device_token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
                "ngrok-skip-browser-warning": "1",
                "User-Agent": "RemoteMonitorAgent/2.0",
            }
            requests.post(dc_url, json={"reason": "agent_stopped", "token": self.device_token}, headers=headers, timeout=2.5)
            print("[Signalling] Sent disconnect signal to server.")
        except Exception:
            pass
