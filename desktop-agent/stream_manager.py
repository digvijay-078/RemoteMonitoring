"""
WebRTC Stream Session Manager for Windows Desktop Agent.
Manages active RTCPeerConnection sessions for streaming live Screen & Audio
to one or more authorized Android Tablets.
"""

import asyncio
import io
import json
import time
import threading
import collections
from concurrent.futures import ThreadPoolExecutor
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from socketserver import ThreadingMixIn
import requests
from PIL import Image

try:
    from aiortc import RTCPeerConnection, RTCSessionDescription, RTCIceServer, RTCConfiguration, RTCIceCandidate
    from webrtc_tracks import ScreenVideoTrack, SystemAudioTrack
except ImportError:
    RTCPeerConnection = object
    RTCSessionDescription = object
    RTCIceServer = object
    RTCConfiguration = object
    RTCIceCandidate = object
    ScreenVideoTrack = None
    SystemAudioTrack = None

from capture_screen import ScreenCapture
from capture_audio import AudioCapture
try:
    from mic_player import RemoteMicPlayer
except ImportError:
    RemoteMicPlayer = None


import sys
import os
if sys.stdout is None:
    sys.stdout = open(os.devnull, "w")
if sys.stderr is None:
    sys.stderr = open(os.devnull, "w")


class StreamingHTTPServer(ThreadingHTTPServer):
    daemon_threads = True
    allow_reuse_address = True


class MJPEGStreamHandler(BaseHTTPRequestHandler):
    """Fast, zero-latency HTTP MJPEG & Snapshot Frame Server."""
    wbufsize = 0
    timeout = 15

    def log_message(self, format, *args):
        pass

    def do_HEAD(self):
        self.send_response(200)
        self.send_header('Content-Type', 'image/jpeg')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()

    def do_POST(self):
        # Push endpoint for remote desktop agents: /stream/<uuid>/push
        if '/push' in self.path:
            try:
                length = int(self.headers.get('Content-Length', 0))
                if length > 0:
                    data_bytes = self.rfile.read(length)
                    parts = [p for p in self.path.split('?')[0].split('/') if p and p not in ('stream', 'push')]
                    key = parts[0] if parts else 'desk2'
                    if 'audio' in self.path:
                        self.server.stream_manager.push_remote_audio(key, data_bytes)
                    else:
                        self.server.stream_manager.set_remote_jpeg(key, data_bytes)
                    self.send_response(200)
                    self.send_header('Content-Type', 'text/plain')
                    self.send_header('Access-Control-Allow-Origin', '*')
                    self.end_headers()
                    self.wfile.write(b"OK")
                    return
            except Exception:
                pass
        self.send_response(400)
        self.end_headers()

    def do_GET(self):
        # Extract target device key from URL: /stream/<uuid>/...
        parts = [p for p in self.path.split('?')[0].split('/') if p and p not in ('stream', 'frame', 'audio', 'video', 'live')]
        target_key = parts[0] if parts else None
        
        # Identify whether Desktop 2 (Lab Node Demo / Remote Workstation) was requested
        is_desk2 = (target_key is not None and target_key != getattr(self.server.stream_manager, 'device_uuid', '')) or \
                   ('21b70652' in self.path) or ('lab' in self.path.lower()) or ('desk2' in self.path.lower())

        frame_fetcher = (lambda: self.server.stream_manager.get_desk2_jpeg(target_key)) if is_desk2 else self.server.stream_manager.get_latest_jpeg

        # 1. Live System Audio Streaming Endpoint (WAV PCM 48kHz Stereo or raw PCM)
        if 'audio' in self.path:
            is_raw = 'raw=1' in self.path
            self.send_response(200)
            self.send_header('Content-Type', 'application/octet-stream' if is_raw else 'audio/wav')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('X-Accel-Buffering', 'no')
            self.end_headers()

            if not is_raw:
                # Generate 44-byte WAV header with 0x7FFFFFFF (infinite streaming)
                sample_rate = 48000
                channels = 2
                bits_per_sample = 16
                byte_rate = sample_rate * channels * (bits_per_sample // 8)
                block_align = channels * (bits_per_sample // 8)
                data_size = 0x7FFFFFFF

                wav_header = bytearray(b'RIFF')
                wav_header.extend((data_size + 36).to_bytes(4, 'little'))
                wav_header.extend(b'WAVEfmt ')
                wav_header.extend((16).to_bytes(4, 'little'))
                wav_header.extend((1).to_bytes(2, 'little'))
                wav_header.extend(channels.to_bytes(2, 'little'))
                wav_header.extend(sample_rate.to_bytes(4, 'little'))
                wav_header.extend(byte_rate.to_bytes(4, 'little'))
                wav_header.extend(block_align.to_bytes(2, 'little'))
                wav_header.extend(bits_per_sample.to_bytes(2, 'little'))
                wav_header.extend(b'data')
                wav_header.extend(data_size.to_bytes(4, 'little'))

                try:
                    self.wfile.write(wav_header)
                    self.wfile.flush()
                except Exception:
                    return

            mgr = self.server.stream_manager
            while getattr(mgr, 'http_streaming_active', True):
                try:
                    if is_desk2:
                        samples_bytes = mgr.get_desk2_audio_chunk(target_key)
                    else:
                        if hasattr(mgr.audio_capture, 'read_available_samples_bytes'):
                            samples_bytes = mgr.audio_capture.read_available_samples_bytes(max_chunks=4)
                        else:
                            samples = mgr.audio_capture.read_frame_samples()
                            samples_bytes = samples.tobytes() if samples is not None else None

                    if samples_bytes and len(samples_bytes) > 0:
                        self.wfile.write(samples_bytes)
                        self.wfile.flush()
                    else:
                        time.sleep(0.008)
                except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError):
                    break
                except Exception:
                    break
            return

        # 2. Frame snapshot (instant preview)
        if 'frame' in self.path:
            frame_data = frame_fetcher()
            if not frame_data:
                frame_data = self.server.stream_manager.get_fallback_standby_jpeg(target_key)
            if frame_data:
                self.send_response(200)
                self.send_header('Content-Type', 'image/jpeg')
                self.send_header('Content-Length', str(len(frame_data)))
                self.send_header('Access-Control-Allow-Origin', '*')
                self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
                self.end_headers()
                try:
                    self.wfile.write(frame_data)
                except Exception:
                    pass
            else:
                self.send_error(503, "Frame not ready")
            return
        # 3. Continuous MJPEG video stream (30 FPS fallback)
        elif 'video' in self.path or 'live' in self.path or 'stream' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()

            fps = getattr(self.server.stream_manager, 'fps', 30)
            target_interval = 1.0 / max(1, fps)
            last_frame = None
            last_sent = 0.0

            while getattr(self.server.stream_manager, 'http_streaming_active', True):
                t0 = time.time()
                frame_data = frame_fetcher()
                if not frame_data:
                    frame_data = self.server.stream_manager.get_fallback_standby_jpeg(target_key)

                if frame_data and (frame_data != last_frame or (t0 - last_sent) > 0.5):
                    last_frame = frame_data
                    last_sent = t0
                    try:
                        self.wfile.write(b'--frame\r\n')
                        self.wfile.write(b'Content-Type: image/jpeg\r\n')
                        self.wfile.write(f'Content-Length: {len(frame_data)}\r\n\r\n'.encode('ascii'))
                        self.wfile.write(frame_data)
                        self.wfile.write(b'\r\n')
                        self.wfile.flush()
                    except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError):
                        break

                elapsed = time.time() - t0
                rem = target_interval - elapsed
                if rem > 0:
                    time.sleep(rem)
                else:
                    time.sleep(0.002)
        else:
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            self.wfile.write(b"RemoteMonitor Stream Engine OK")


class StreamManager:
    """Manages WebRTC PeerConnections and high-speed HTTP streams for multiple tablet viewers."""

    def __init__(self, server_url: str, device_token: str, device_uuid: str = None, fps: int = 30):
        self.server_url = server_url.rstrip("/")
        self.device_token = device_token
        self.device_uuid = device_uuid or "unknown"
        self.fps = fps

        # Shared capture singletons (efficient GPU & audio resource usage)
        self.screen_capture = ScreenCapture(target_fps=fps)
        self.audio_capture = AudioCapture()

        # Map of session_id -> RTCPeerConnection
        self.active_sessions: dict[str, RTCPeerConnection] = {}

        # Universal High-Speed HTTP/MJPEG Streaming Engine (Bypasses CGNAT/NAT everywhere)
        self._latest_jpeg: bytes = None
        self._latest_jpeg_desk2: bytes = None
        self._remote_jpegs: dict[str, bytes] = {}
        self._remote_jpegs_timestamp: dict[str, float] = {}
        self._remote_jpegs_lock = threading.Lock()

        # FIFO Audio Queues with Lip-Sync Jitter Management
        self._remote_audio_queues: dict[str, collections.deque] = collections.defaultdict(lambda: collections.deque(maxlen=30))
        self._remote_audio_lock = threading.Lock()
        self._jpeg_lock = threading.Lock()
        self.http_streaming_active = True

        # Start background capture and compression worker thread
        self._capture_thread = threading.Thread(target=self._mjpeg_worker, daemon=True)
        self._capture_thread.start()

        # Dedicated Video & Audio Push Workers (Feeds Central Stream Hub)
        self._video_push_thread = threading.Thread(target=self._video_push_worker, daemon=True)
        self._video_push_thread.start()
        self._audio_push_thread = threading.Thread(target=self._audio_push_worker, daemon=True)
        self._audio_push_thread.start()
        print(f"[StreamManager] Active independent video & audio push workers to {self.server_url}/stream/{self.device_uuid}/push")

        # Dedicated Central Hub (tools/server_stream_hub.py) manages 8085; agent acts purely as streamer & player
        self.http_server = None

        # Start Realtime Reverse Mic Intercom Audio Player (Tablet -> PC Speaker)
        self.mic_player = RemoteMicPlayer(self.server_url, self.device_uuid)
        self.mic_player.start()

    def get_headers(self):
        return {
            "Authorization": f"Bearer {self.device_token}",
            "Content-Type": "application/json",
            "ngrok-skip-browser-warning": "1",
            "User-Agent": "RemoteMonitorAgent/2.0"
        }

    def push_remote_audio(self, key: str, data: bytes):
        """Append received audio bytes to the remote device FIFO queue."""
        chunk_size = 3840  # 20ms at 48kHz stereo 16-bit
        with self._remote_audio_lock:
            q = self._remote_audio_queues[key]
            # Drop older chunks if queue exceeds 15 chunks (300ms) to maintain real-time lip-sync
            while len(q) > 15:
                q.popleft()
            for i in range(0, len(data), chunk_size):
                chunk = data[i:i + chunk_size]
                if len(chunk) == chunk_size:
                    q.append((chunk, time.time()))

    def get_desk2_audio_chunk(self, key: str = None) -> bytes:
        """Pop next audio chunk for Desktop 2. Returns None if empty (never repeats stale audio)."""
        with self._remote_audio_lock:
            if key and key in self._remote_audio_queues:
                queues = [self._remote_audio_queues[key]]
            else:
                queues = list(self._remote_audio_queues.values())

            for q in queues:
                while len(q) > 0:
                    chunk, ts = q.popleft()
                    # Drop chunks older than 0.35s to prevent audio latency accumulation
                    if time.time() - ts < 0.35:
                        return chunk
        return None

    def _video_push_worker(self):
        """Dedicated thread pushing the freshest JPEG frame to central server."""
        import urllib.parse
        parsed = urllib.parse.urlparse(self.server_url)
        base_url = self.server_url
        if parsed.hostname in ('127.0.0.1', 'localhost') and parsed.port == 8000:
            base_url = "http://127.0.0.1:8088"
        push_url = f"{base_url}/stream/{self.device_uuid}/push"
        delay = 1.0 / max(1, self.fps)
        session = requests.Session()
        session.headers.update({"Connection": "keep-alive"})
        adapter = requests.adapters.HTTPAdapter(pool_connections=2, pool_maxsize=4)
        session.mount('https://', adapter)
        session.mount('http://', adapter)

        while self.http_streaming_active:
            # If WebRTC is actively streaming live media, slow down HTTP push to 1 FPS preview
            has_live_webrtc = any(
                getattr(pc, 'connectionState', '') == 'connected'
                for pc in list(self.active_sessions.values())
            )
            if has_live_webrtc:
                time.sleep(1.0)
                continue

            t0 = time.time()
            frame = self.get_latest_jpeg()
            if frame:
                try:
                    session.post(push_url, data=frame, headers={'Content-Type': 'image/jpeg'}, timeout=0.8)
                except Exception:
                    pass

            elapsed = time.time() - t0
            sleep_time = delay - elapsed
            if sleep_time > 0:
                time.sleep(sleep_time)
            else:
                time.sleep(0.005)

    def _audio_push_worker(self):
        """Dedicated thread pushing audio chunks continuously with zero interference from video."""
        import urllib.parse
        parsed = urllib.parse.urlparse(self.server_url)
        base_url = self.server_url
        if parsed.hostname in ('127.0.0.1', 'localhost') and parsed.port == 8000:
            base_url = "http://127.0.0.1:8088"
        push_audio_url = f"{base_url}/stream/{self.device_uuid}/push?audio=1"
        session = requests.Session()
        session.headers.update({"Connection": "keep-alive"})
        adapter = requests.adapters.HTTPAdapter(pool_connections=2, pool_maxsize=4)
        session.mount('https://', adapter)
        session.mount('http://', adapter)

        while self.http_streaming_active:
            # If WebRTC is active, audio is streamed via Opus directly -> pause HTTP audio push
            has_live_webrtc = any(
                getattr(pc, 'connectionState', '') == 'connected'
                for pc in list(self.active_sessions.values())
            )
            if has_live_webrtc:
                time.sleep(1.0)
                continue

            t0 = time.time()
            try:
                if hasattr(self.audio_capture, 'read_available_samples_bytes'):
                    data_bytes = self.audio_capture.read_available_samples_bytes(max_chunks=4)
                else:
                    samples = self.audio_capture.read_frame_samples()
                    data_bytes = samples.tobytes() if samples is not None else None

                if data_bytes and len(data_bytes) > 0:
                    session.post(push_audio_url, data=data_bytes, headers={'Content-Type': 'application/octet-stream'}, timeout=0.5)
            except Exception:
                pass

            elapsed = time.time() - t0
            sleep_time = 0.040 - elapsed
            if sleep_time > 0:
                time.sleep(sleep_time)
            else:
                time.sleep(0.005)

    def _mjpeg_worker(self):
        """Dedicated thread encoding latest screen frames to JPEG."""
        target_interval = 1.0 / max(1, self.fps)
        has_cv2 = False
        try:
            import cv2
            has_cv2 = True
        except ImportError:
            has_cv2 = False

        if hasattr(self.screen_capture, '_attach_to_input_desktop'):
            self.screen_capture._attach_to_input_desktop()

        last_desk2_render = 0.0

        while self.http_streaming_active:
            t0 = time.time()
            try:
                frame = self.screen_capture.capture_frame()
                if frame is not None:
                    jpeg_bytes = None
                    if has_cv2:
                        h, w = frame.shape[:2]
                        if w > 854:
                            target_w = 854
                            target_h = int(h * (854.0 / w))
                            small = cv2.resize(frame, (target_w, target_h), interpolation=cv2.INTER_LINEAR)
                        else:
                            small = frame

                        ret, buf = cv2.imencode('.jpg', small, [int(cv2.IMWRITE_JPEG_QUALITY), 42])
                        if ret:
                            jpeg_bytes = buf.tobytes()
                    else:
                        rgb = frame[:, :, ::-1]
                        pil_img = Image.fromarray(rgb)
                        if pil_img.width > 854:
                            new_h = int(pil_img.height * (854.0 / pil_img.width))
                            pil_img = pil_img.resize((854, new_h), Image.BILINEAR)
                        buf = io.BytesIO()
                        pil_img.save(buf, format='JPEG', quality=42)
                        jpeg_bytes = buf.getvalue()

                    if jpeg_bytes:
                        with self._jpeg_lock:
                            self._latest_jpeg = jpeg_bytes

                # Standby Screen: Render at most once every 5 seconds to eliminate memory thrashing
                if has_cv2 and (t0 - last_desk2_render > 5.0):
                    last_desk2_render = t0
                    import numpy as np
                    standby = np.zeros((720, 1280, 3), dtype=np.uint8)
                    standby[:] = (26, 15, 10)
                    cv2.rectangle(standby, (0, 0), (1280, 60), (35, 23, 15), -1)
                    cv2.putText(standby, "DESK-LAB-02  |  Secondary Workstation (PC 2)", (30, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.85, (230, 235, 245), 2)
                    time_str = time.strftime("%H:%M:%S")
                    cv2.putText(standby, f"STATUS: STANDBY ({time_str})", (950, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (245, 158, 11), 2)

                    cv2.rectangle(standby, (240, 200), (1040, 520), (30, 20, 15), -1)
                    cv2.rectangle(standby, (240, 200), (1040, 520), (55, 38, 25), 2)
                    cv2.putText(standby, "WAITING FOR PC 2 AGENT TO CONNECT", (310, 290), cv2.FONT_HERSHEY_SIMPLEX, 1.0, (255, 255, 255), 2)
                    cv2.putText(standby, "Start the agent on PC 2 to view its real desktop screen:", (320, 350), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (160, 175, 195), 1)
                    cv2.putText(standby, "run_pc2.bat   OR   python agent.py start", (370, 410), cv2.FONT_HERSHEY_SIMPLEX, 0.85, (52, 211, 153), 2)
                    cv2.putText(standby, "Live screen will automatically appear here once connected.", (350, 470), cv2.FONT_HERSHEY_SIMPLEX, 0.60, (140, 155, 175), 1)

                    ret2, buf2 = cv2.imencode('.jpg', standby, [int(cv2.IMWRITE_JPEG_QUALITY), 75])
                    if ret2:
                        with self._jpeg_lock:
                            self._latest_jpeg_desk2 = buf2.tobytes()
            except Exception:
                pass

            elapsed = time.time() - t0
            # Only throttle to 5 FPS if WebRTC is actively CONNECTED (so HTTP viewers get full 30 FPS)
            has_active_webrtc = any(getattr(pc, 'connectionState', '') == 'connected' for pc in self.active_sessions.values())
            effective_interval = (1.0 / 5.0) if has_active_webrtc else target_interval
            sleep_time = effective_interval - elapsed
            if sleep_time > 0:
                time.sleep(sleep_time)

    def _create_standby_image(self, title: str, subtitle: str) -> bytes:
        try:
            import cv2
            import numpy as np
            img = np.zeros((720, 1280, 3), dtype=np.uint8)
            img[:] = (20, 15, 12)
            cv2.rectangle(img, (0, 0), (1280, 60), (35, 23, 15), -1)
            cv2.putText(img, title, (30, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.85, (230, 235, 245), 2)
            time_str = time.strftime("%H:%M:%S")
            cv2.putText(img, f"STANDBY ({time_str})", (960, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (245, 158, 11), 2)
            cv2.rectangle(img, (200, 180), (1080, 540), (28, 20, 16), -1)
            cv2.rectangle(img, (200, 180), (1080, 540), (55, 38, 25), 2)
            cv2.putText(img, subtitle, (260, 320), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (255, 255, 255), 2)
            cv2.putText(img, "Live screen buffer syncing... Please wait", (320, 390), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (160, 175, 195), 1)
            ret, buf = cv2.imencode('.jpg', img, [int(cv2.IMWRITE_JPEG_QUALITY), 75])
            if ret:
                return buf.tobytes()
        except Exception:
            pass
        return None

    def get_fallback_standby_jpeg(self, key: str = None) -> bytes:
        is_desk2 = (key is not None and key != getattr(self, 'device_uuid', '')) or (key and ('lab' in str(key).lower() or '21b7' in str(key)))
        name = "DESK-LAB-02  |  Secondary Workstation (PC 2)" if is_desk2 else f"DESK-HQ-01  |  {getattr(self, 'device_uuid', '')}"
        status = "SECONDARY WORKSTATION (AWAITING AGENT)" if is_desk2 else "LOCAL WORKSTATION (INITIALIZING...)"
        standby = self._create_standby_image(name, status)
        return standby

    def get_latest_jpeg(self) -> bytes:
        """Return latest captured and compressed JPEG frame bytes for Desktop 1."""
        with self._jpeg_lock:
            return self._latest_jpeg or self.get_fallback_standby_jpeg()

    def set_remote_jpeg(self, key: str, data: bytes):
        """Save a pushed JPEG frame received from a remote PC."""
        with self._remote_jpegs_lock:
            self._remote_jpegs[key] = data
            self._remote_jpegs_timestamp[key] = time.time()

    def get_desk2_jpeg(self, key: str = None) -> bytes:
        """Return latest captured frame from PC 2 if pushed recently, otherwise fallback to local/standby."""
        with self._remote_jpegs_lock:
            if key and key in self._remote_jpegs:
                if time.time() - self._remote_jpegs_timestamp.get(key, 0) < 6.0:
                    return self._remote_jpegs[key]
            for k, data in self._remote_jpegs.items():
                if time.time() - self._remote_jpegs_timestamp.get(k, 0) < 6.0:
                    return data
        return self._latest_jpeg_desk2 or self.get_fallback_standby_jpeg('desk2')

    def get_headers(self) -> dict:
        return {
            "Authorization": f"Bearer {self.device_token}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

    async def handle_signalling_event(self, event_type: str, payload: dict):
        """Process incoming Reverb events."""
        session_id = payload.get("session_id")
        if not session_id:
            return

        if event_type == "webrtc.session.requested":
            await self._handle_session_requested(session_id, payload)

        elif event_type == "webrtc.signal.answer":
            await self._handle_signal_answer(session_id, payload)

        elif event_type == "webrtc.signal.ice_candidate":
            await self._handle_signal_ice_candidate(session_id, payload)

        elif event_type == "webrtc.session.status":
            await self._handle_session_status(session_id, payload)

    async def _handle_session_requested(self, session_id: str, payload: dict):
        """Tablet requested a new streaming session -> create WebRTC PC & send Offer."""
        tablet_uuid = payload.get("tablet_uuid", "unknown")
        print(f"[StreamManager] Session request received for session {session_id} from Tablet {tablet_uuid}")

        # Clean up existing session if duplicate
        if session_id in self.active_sessions:
            try:
                await self.active_sessions[session_id].close()
            except Exception:
                pass
            del self.active_sessions[session_id]

        # Parse ICE servers
        ice_servers_data = payload.get("ice_servers", [])
        rtc_ice_servers = []
        for s in ice_servers_data:
            urls = s.get("urls")
            if urls:
                rtc_ice_servers.append(RTCIceServer(urls=urls, username=s.get("username"), credential=s.get("credential")))

        config = RTCConfiguration(iceServers=rtc_ice_servers if rtc_ice_servers else [RTCIceServer("stun:stun.l.google.com:19302")])
        pc = RTCPeerConnection(configuration=config)
        self.active_sessions[session_id] = pc

        # Attach live screen and system audio tracks
        video_track = ScreenVideoTrack(self.screen_capture, fps=self.fps)
        audio_track = SystemAudioTrack(self.audio_capture)

        pc.addTrack(video_track)
        pc.addTrack(audio_track)

        # Handle connection state changes
        @pc.on("connectionstatechange")
        async def on_state_change():
            state = pc.connectionState
            print(f"[StreamManager] Session {session_id} connection state: {state}")
            if state == "connected":
                await self._notify_server_status(session_id, "connected")
                try:
                    local_cands = [l for l in (pc.localDescription.sdp.splitlines() if pc.localDescription else []) if "candidate:" in l]
                    remote_cands = [l for l in (pc.remoteDescription.sdp.splitlines() if pc.remoteDescription else []) if "candidate:" in l]
                    print(f"[StreamManager] Session {session_id} connected. Local candidates: {len(local_cands)}, Remote candidates: {len(remote_cands)}")
                    for c in local_cands:
                        print(f"   [Desktop ICE Local] {c}")
                except Exception:
                    pass
            elif state in ["failed", "closed"]:
                await self._notify_server_status(session_id, "terminated", reason=f"WebRTC state {state}")
                if session_id in self.active_sessions:
                    del self.active_sessions[session_id]

        # Handle ICE candidates generated by local peer
        @pc.on("icecandidate")
        async def on_ice_candidate(candidate):
            if candidate:
                await self._send_ice_candidate(session_id, candidate)

        # Create SDP Offer
        try:
            offer = await pc.createOffer()
            await pc.setLocalDescription(offer)

            # Send Offer to Server
            await self._send_offer(session_id, pc.localDescription)
            print(f"[StreamManager] Sent SDP Offer for session {session_id}")
        except Exception as e:
            print(f"[StreamManager] Error creating/sending offer: {e}")

    async def _handle_signal_answer(self, session_id: str, payload: dict):
        """Tablet sent SDP Answer -> apply to PeerConnection."""
        pc = self.active_sessions.get(session_id)
        if not pc:
            print(f"[StreamManager] Received answer for unknown session {session_id}")
            return

        sdp_info = payload.get("sdp", {})
        if not sdp_info:
            return

        try:
            raw_sdp = sdp_info.get("sdp", "")
            lines = [l.strip() for l in raw_sdp.replace("\r\n", "\n").replace("\r", "\n").split("\n") if l.strip()]
            clean_sdp = "\r\n".join(lines) + "\r\n"
            desc = RTCSessionDescription(type=sdp_info.get("type", "answer"), sdp=clean_sdp)
            await pc.setRemoteDescription(desc)
            print(f"[StreamManager] Applied remote SDP Answer for session {session_id}")
        except Exception as e:
            print(f"[StreamManager] Error setting remote description: {e}")

    async def _handle_signal_ice_candidate(self, session_id: str, payload: dict):
        """Tablet sent ICE Candidate -> add to PeerConnection."""
        pc = self.active_sessions.get(session_id)
        if not pc:
            return

        cand_data = payload.get("candidate", {})
        if not cand_data or not cand_data.get("candidate"):
            return

        try:
            candidate = RTCIceCandidate(
                candidate=cand_data["candidate"],
                sdpMid=cand_data.get("sdpMid"),
                sdpMLineIndex=cand_data.get("sdpMLineIndex")
            )
            await pc.addIceCandidate(candidate)
        except Exception:
            pass

    async def _handle_session_status(self, session_id: str, payload: dict):
        """Session terminated or status updated."""
        status = payload.get("status")
        if status in ["terminated", "failed"]:
            pc = self.active_sessions.pop(session_id, None)
            if pc:
                try:
                    await pc.close()
                except Exception:
                    pass
                print(f"[StreamManager] Closed session {session_id} on termination event.")

    async def _send_offer(self, session_id: str, local_desc):
        """POST SDP Offer to Laravel Control Plane."""
        url = f"{self.server_url}/api/v1/webrtc/signal/offer"
        body = {
            "session_id": session_id,
            "sdp": {
                "type": local_desc.type,
                "sdp": local_desc.sdp,
            }
        }
        loop = asyncio.get_running_loop()
        try:
            await loop.run_in_executor(None, lambda: requests.post(url, json=body, headers=self.get_headers(), timeout=10))
        except Exception as e:
            print(f"[StreamManager] Error sending SDP offer: {e}")

    async def _send_ice_candidate(self, session_id: str, candidate):
        """POST local ICE candidate to Laravel Control Plane."""
        url = f"{self.server_url}/api/v1/webrtc/signal/ice-candidate"
        cand_dict = {
            "candidate": candidate.candidate,
            "sdpMid": candidate.sdpMid,
            "sdpMLineIndex": candidate.sdpMLineIndex,
        }
        body = {
            "session_id": session_id,
            "candidate": cand_dict,
        }
        loop = asyncio.get_running_loop()
        try:
            await loop.run_in_executor(None, lambda: requests.post(url, json=body, headers=self.get_headers(), timeout=5))
        except Exception:
            pass

    async def _notify_server_status(self, session_id: str, status: str, reason: str = None):
        """POST Session status update to Laravel Control Plane."""
        url = f"{self.server_url}/api/v1/webrtc/session/status"
        body = {
            "session_id": session_id,
            "status": status,
            "reason": reason,
        }
        loop = asyncio.get_running_loop()
        try:
            await loop.run_in_executor(None, lambda: requests.post(url, json=body, headers=self.get_headers(), timeout=10))
        except Exception as e:
            print(f"[StreamManager] Error notifying server status: {e}")

    async def close_all(self):
        """Cleanly close all active sessions and capture devices."""
        self.http_streaming_active = False
        if hasattr(self, 'http_server') and self.http_server:
            try:
                self.http_server.shutdown()
            except Exception:
                pass
        for session_id, pc in list(self.active_sessions.items()):
            try:
                await pc.close()
            except Exception:
                pass
        self.active_sessions.clear()
        self.screen_capture.close()
        self.audio_capture.close()
        if hasattr(self, 'mic_player') and self.mic_player:
            self.mic_player.stop()
