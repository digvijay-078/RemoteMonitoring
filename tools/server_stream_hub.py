"""
RemoteMonitor Server-Side Stream Relay Hub.
Listens on 0.0.0.0:8085 to receive pushed frames and audio from remote Desktop Agents
and serves real-time MJPEG live feeds and audio to Admin Control Room and Tablets.
"""

import io
import time
import json
import threading
import collections
import socketserver
from http.server import HTTPServer, BaseHTTPRequestHandler
from PIL import Image, ImageDraw, ImageFont

class StreamHubServer(socketserver.ThreadingMixIn, HTTPServer):
    allow_reuse_address = True
    daemon_threads = True

    def __init__(self, server_address, RequestHandlerClass):
        super().__init__(server_address, RequestHandlerClass)
        self.frames = {}
        self.frame_timestamps = {}
        self.audio_queues = collections.defaultdict(lambda: collections.deque(maxlen=20))
        self.lock = threading.Lock()
        self.audio_lock = threading.Lock()
        self.mic_queues = collections.defaultdict(lambda: collections.deque(maxlen=30))
        self.mic_lock = threading.Lock()
        self.standby_cache = {}

    def get_active_streams(self) -> dict:
        now = time.time()
        with self.lock:
            return {
                k: {
                    "last_ts": ts,
                    "age_seconds": round(now - ts, 2),
                    "active": (now - ts) < 4.5
                }
                for k, ts in self.frame_timestamps.items()
            }

    def _normalize_key(self, key: str) -> str:
        if not key:
            return ""
        return str(key).strip().lower().replace("-", "")

    def set_frame(self, key: str, data: bytes):
        with self.lock:
            k = self._normalize_key(key)
            if k:
                self.frames[k] = data
                self.frame_timestamps[k] = time.time()

    def get_frame(self, key: str) -> bytes:
        target_k = self._normalize_key(key)
        now = time.time()
        with self.lock:
            # 1. Exact match
            if target_k in self.frames:
                if (now - self.frame_timestamps.get(target_k, 0)) < 4.0:
                    return self.frames[target_k]

            # 2. Match if either key is a strong prefix of the other (>= 8 chars to avoid cross-device collision)
            if len(target_k) >= 8:
                for existing_k, f in self.frames.items():
                    if len(existing_k) >= 8:
                        if existing_k.startswith(target_k) or target_k.startswith(existing_k):
                            if (now - self.frame_timestamps.get(existing_k, 0)) < 4.0:
                                return f

        # Device is not streaming or offline: return device-specific standby frame
        return self.get_standby_frame(key)

    def push_audio(self, key: str, data: bytes):
        chunk_size = 3840  # 20ms at 48kHz stereo 16-bit
        k = self._normalize_key(key)
        if not k:
            return
        with self.audio_lock:
            q = self.audio_queues[k]
            for i in range(0, len(data), chunk_size):
                chunk = data[i:i + chunk_size]
                if len(chunk) == chunk_size:
                    q.append((chunk, time.time()))

    def pop_audio_chunk(self, key: str) -> bytes | None:
        target_k = self._normalize_key(key)
        if not target_k:
            return None
        with self.audio_lock:
            target_q = None
            if target_k in self.audio_queues:
                target_q = self.audio_queues[target_k]
            elif len(target_k) >= 8:
                for existing_k, q in self.audio_queues.items():
                    if len(existing_k) >= 8 and (existing_k.startswith(target_k) or target_k.startswith(existing_k)):
                        target_q = q
                        break

            if target_q and len(target_q) > 0:
                while len(target_q) > 0:
                    chunk, ts = target_q.popleft()
                    if time.time() - ts < 0.4:
                        return chunk
        return None

    def push_mic_audio(self, key: str, data: bytes):
        """Append incoming microphone PCM audio from tablet to queue."""
        k = self._normalize_key(key)
        if not k:
            return
        with self.mic_lock:
            q = self.mic_queues[k]
            q.append((data, time.time()))

    def pop_mic_chunk(self, key: str) -> bytes | None:
        """Pop oldest mic audio chunk for target desktop. Drops stale chunks (>2.0s)."""
        target_k = self._normalize_key(key)
        if not target_k:
            return None
        with self.mic_lock:
            target_q = None
            if target_k in self.mic_queues:
                target_q = self.mic_queues[target_k]
            elif len(target_k) >= 8:
                for existing_k, q in self.mic_queues.items():
                    if len(existing_k) >= 8 and (existing_k.startswith(target_k) or target_k.startswith(existing_k)):
                        target_q = q
                        break

            if target_q and len(target_q) > 0:
                while len(target_q) > 0:
                    chunk, ts = target_q.popleft()
                    if time.time() - ts < 2.0:
                        return chunk
        return None

    def get_standby_frame(self, key: str) -> bytes:
        now_bucket = int(time.time() * 2)  # changes every 0.5s for subtle pulse
        display_key = str(key or 'Workstation')
        cache_key = f"{display_key}_{now_bucket}"
        if cache_key in self.standby_cache:
            return self.standby_cache[cache_key]

        img = Image.new('RGB', (1280, 720), color=(15, 23, 42))
        draw = ImageDraw.Draw(img)

        # Draw tech border
        draw.rectangle([(20, 20), (1260, 700)], outline=(51, 65, 85), width=2)
        draw.rectangle([(24, 24), (1256, 704)], outline=(30, 41, 59), width=1)

        # Header bar
        draw.rectangle([(22, 22), (1258, 60)], fill=(30, 27, 75))
        draw.text((40, 32), "REMOTEMONITOR LIVE STREAM ENGINE - 30 FPS", fill=(165, 180, 252))

        # Center status
        pulse_dot = "⚪" if (now_bucket % 2 == 0) else "📡"
        draw.text((400, 290), f"{pulse_dot} AWAITING WORKSTATION BROADCAST FEED", fill=(148, 163, 184))
        draw.text((400, 335), f"Target Device: {display_key}", fill=(203, 213, 225))
        draw.text((400, 375), "Status: Workstation Agent Standby / Awaiting Frames", fill=(99, 102, 241))
        draw.text((400, 415), "Live video will stream automatically when client starts broadcast.", fill=(100, 116, 139))

        # Bottom timestamp
        ts_str = time.strftime("%Y-%m-%d %H:%M:%S IST")
        draw.text((40, 665), f"GATEWAY SYNC: {ts_str}", fill=(71, 85, 105))

        buf = io.BytesIO()
        img.save(buf, format='JPEG', quality=75)
        val = buf.getvalue()
        self.standby_cache[cache_key] = val
        if len(self.standby_cache) > 50:
            self.standby_cache.clear()
        return val


class StreamHubHandler(BaseHTTPRequestHandler):
    wbufsize = 0
    timeout = 15

    def log_message(self, format, *args):
        pass

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, HEAD')
        self.send_header('Access-Control-Allow-Headers', '*')
        self.end_headers()

    def do_HEAD(self):
        self.send_response(200)
        self.send_header('Content-Type', 'image/jpeg')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()

    def do_POST(self):
        # 1. Reverse Mic Intercom Audio Push from Tablet: /stream/<uuid>/mic
        if '/mic' in self.path:
            try:
                length = int(self.headers.get('Content-Length', 0))
                if length > 0:
                    data_bytes = self.rfile.read(length)
                    parts = [p for p in self.path.split('?')[0].split('/') if p and p not in ('stream', 'mic', 'push', 'mic_push')]
                    key = parts[0] if parts else 'default'
                    self.server.push_mic_audio(key, data_bytes)
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
            return

        # 2. Forward Video & Audio Push from Desktop Agent: /stream/<uuid>/push
        if '/push' in self.path:
            try:
                length = int(self.headers.get('Content-Length', 0))
                if length > 0:
                    data_bytes = self.rfile.read(length)
                    parts = [p for p in self.path.split('?')[0].split('/') if p and p not in ('stream', 'push')]
                    key = parts[0] if parts else 'default'
                    if 'audio=1' in self.path or 'audio' in self.path:
                        self.server.push_audio(key, data_bytes)
                    else:
                        self.server.set_frame(key, data_bytes)
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
        # Active streams status JSON query
        if self.path.startswith('/api/active-streams') or self.path.startswith('/stream/active-status'):
            active = self.server.get_active_streams()
            payload = json.dumps({"success": True, "streams": active, "timestamp": time.time()}).encode('utf-8')
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(payload)))
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.end_headers()
            self.wfile.write(payload)
            return

        parts = [p for p in self.path.split('?')[0].split('/') if p and p not in ('stream', 'frame', 'audio', 'video', 'live', 'mic', 'mic_stream')]
        key = parts[0] if parts else 'default'

        # 0. Reverse Mic Audio Stream for Desktop Agent (Tablet Voice -> Desktop Speakers)
        if 'mic_stream' in self.path or 'mic_audio' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'application/octet-stream')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('X-Accel-Buffering', 'no')
            self.end_headers()

            last_activity = time.time()
            while True:
                try:
                    chunk = self.server.pop_mic_chunk(key)
                    if chunk:
                        self.wfile.write(chunk)
                        self.wfile.flush()
                        last_activity = time.time()
                    else:
                        now = time.time()
                        if now - last_activity > 4.0:
                            # Send silent keep-alive heartbeat so stream never times out
                            self.wfile.write(b'\x00\x00\x00\x00')
                            self.wfile.flush()
                            last_activity = now
                        time.sleep(0.01)
                except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError):
                    break
                except Exception:
                    break
            return

        # 1. Live System Audio Streaming Endpoint (Desktop -> Tablet)
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

            while True:
                try:
                    chunk = self.server.pop_audio_chunk(key)
                    if chunk:
                        self.wfile.write(chunk)
                        self.wfile.flush()
                    else:
                        time.sleep(0.01)
                except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError):
                    break
                except Exception:
                    break
            return

        # 2. Single Frame Snapshot
        if 'frame' in self.path:
            frame_data = self.server.get_frame(key)
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
            return

        # Status / Health check
        if 'status' in self.path or 'health' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            with self.server.lock:
                active = list(self.server.frames.keys())
            status = {
                "status": "online",
                "active_streams": active,
                "timestamp": time.time()
            }
            self.wfile.write(json.dumps(status).encode('utf-8'))
            return

        # 3. Continuous MJPEG Video Stream
        if 'live' in self.path or 'video' in self.path or 'mjpeg' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-cache, no-store, must-revalidate')
            self.send_header('Pragma', 'no-cache')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()

            target_interval = 1.0 / 30
            last_frame = None
            last_sent = 0.0

            while True:
                t0 = time.time()
                frame_data = self.server.get_frame(key)
                if frame_data and (frame_data != last_frame or (t0 - last_sent) > 0.5):
                    try:
                        self.wfile.write(b"--frame\r\n")
                        self.wfile.write(b"Content-Type: image/jpeg\r\n")
                        self.wfile.write(f"Content-Length: {len(frame_data)}\r\n\r\n".encode("ascii"))
                        self.wfile.write(frame_data)
                        self.wfile.write(b"\r\n")
                        last_frame = frame_data
                        last_sent = t0
                    except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError, OSError):
                        break
                    except Exception:
                        break

                elapsed = time.time() - t0
                sleep_time = target_interval - elapsed
                if sleep_time > 0:
                    time.sleep(sleep_time)
                else:
                    time.sleep(0.005)
            return

        # Default fallback: return single frame
        frame_data = self.server.get_frame(key)
        self.send_response(200)
        self.send_header('Content-Type', 'image/jpeg')
        self.send_header('Content-Length', str(len(frame_data)))
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        try:
            self.wfile.write(frame_data)
        except Exception:
            pass


def run_hub():
    port = 8085
    server = StreamHubServer(('0.0.0.0', port), StreamHubHandler)
    print(f"[Stream Relay Hub] Listening on 0.0.0.0:{port}...")
    server.serve_forever()


if __name__ == '__main__':
    run_hub()
