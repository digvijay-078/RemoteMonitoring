"""
RemoteMonitor Desktop Agent - Realtime Reverse Mic Intercom Audio Player.
Receives live voice instruction stream from Tablet over HTTP chunked stream
and plays directly to Windows default playback device (Speakers / Headphones).
"""

import sys
import os
import time
import threading
import requests

_HAS_PYAUDIOWPATCH = False
try:
    import pyaudiowpatch as pyaudio
    _HAS_PYAUDIOWPATCH = True
except ImportError:
    pyaudio = None


class RemoteMicPlayer:
    """
    Listens for incoming voice instructions from authorized tablets
    and outputs the sound through the computer's default speakers.
    """

    SAMPLE_RATE = 24000  # 24kHz Mono 16-bit PCM for crisp speech clarity
    CHANNELS = 1
    FRAMES_PER_BUFFER = 1024

    def __init__(self, server_url: str, device_uuid: str):
        self.server_url = server_url.rstrip("/")
        self.device_uuid = device_uuid
        self.is_running = False
        self._thread = None
        self._pa = None
        self._out_stream = None

    def start(self):
        """Start background worker listening for tablet mic audio."""
        if self.is_running:
            return
        self.is_running = True
        self._thread = threading.Thread(target=self._run_loop, daemon=True, name="RemoteMicPlayer")
        self._thread.start()
        print(f"[RemoteMicPlayer] Active: Listening for tablet voice on {self.server_url}/stream/{self.device_uuid}/mic_stream")

    def stop(self):
        self.is_running = False
        self._close_audio_stream()

    def _init_audio_stream(self):
        if not _HAS_PYAUDIOWPATCH or pyaudio is None:
            return False
        try:
            if self._out_stream and self._out_stream.is_active():
                return True
            if not self._pa:
                self._pa = pyaudio.PyAudio()
            self._out_stream = self._pa.open(
                format=pyaudio.paInt16,
                channels=self.CHANNELS,
                rate=self.SAMPLE_RATE,
                output=True,
                frames_per_buffer=self.FRAMES_PER_BUFFER
            )
            return True
        except Exception as e:
            print(f"[RemoteMicPlayer] Error opening audio playback stream: {e}", file=sys.stderr)
            return False

    def _close_audio_stream(self):
        try:
            if self._out_stream:
                self._out_stream.stop_stream()
                self._out_stream.close()
                self._out_stream = None
            if self._pa:
                self._pa.terminate()
                self._pa = None
        except Exception:
            pass

    def _run_loop(self):
        import urllib.parse
        parsed = urllib.parse.urlparse(self.server_url)
        base_url = self.server_url
        if parsed.hostname in ('127.0.0.1', 'localhost') and parsed.port == 8000:
            base_url = "http://127.0.0.1:8088"

        stream_url = f"{base_url}/stream/{self.device_uuid}/mic_stream"
        session = requests.Session()
        session.headers.update({"Connection": "keep-alive"})

        while self.is_running:
            try:
                # Open audio output stream
                if not self._init_audio_stream():
                    time.sleep(2)
                    continue

                # Connect to mic audio stream with no read timeout so speech pauses don't disconnect
                with session.get(stream_url, stream=True, timeout=(10, None)) as resp:
                    if resp.status_code == 200:
                        print(f"[RemoteMicPlayer] Connected to {stream_url} - streaming tablet voice to desktop speakers")
                        pcm_buf = bytearray()
                        for chunk in resp.iter_content(chunk_size=None):
                            if not self.is_running:
                                break
                            if not chunk:
                                continue
                            # Filter out silent heartbeat keep-alive pulses
                            if len(chunk) <= 8 and all(b == 0 for b in chunk):
                                continue

                            pcm_buf.extend(chunk)
                            # PyAudio 16-bit PCM requires chunks to be multiples of 2 bytes
                            aligned_len = (len(pcm_buf) // 2) * 2
                            if aligned_len > 0:
                                audio_bytes = bytes(pcm_buf[:aligned_len])
                                pcm_buf = pcm_buf[aligned_len:]
                                try:
                                    self._out_stream.write(audio_bytes)
                                except Exception as write_err:
                                    print(f"[RemoteMicPlayer] Audio write error: {write_err}", file=sys.stderr)
                                    self._close_audio_stream()
                                    self._init_audio_stream()
                    else:
                        time.sleep(1.0)
            except (requests.RequestException, OSError):
                time.sleep(1.0)
            except Exception:
                time.sleep(1.0)
