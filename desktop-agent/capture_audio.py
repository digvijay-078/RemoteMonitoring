"""
WASAPI Loopback System Audio Capture for Windows Desktop Agent.
Captures desktop system audio output in realtime via PyAudioWPatch (WASAPI Loopback)
and formats into 20ms chunks (48kHz, stereo, 16-bit PCM) for WebRTC Opus encoding.
"""

import threading
import time
import numpy as np

_HAS_PYAUDIOWPATCH = False
try:
    import pyaudiowpatch as pyaudio
    _HAS_PYAUDIOWPATCH = True
except ImportError:
    pyaudio = None

_HAS_SOUNDDEVICE = False
try:
    import sounddevice as sd
    _HAS_SOUNDDEVICE = True
except ImportError:
    sd = None


class AudioCapture:
    """Realtime WASAPI Loopback system audio capture engine using PyAudioWPatch."""

    SAMPLE_RATE = 48000  # 48kHz standard for WebRTC Opus
    CHANNELS = 2         # Stereo
    FRAME_DURATION_MS = 20
    SAMPLES_PER_FRAME = int(SAMPLE_RATE * (FRAME_DURATION_MS / 1000))  # 960 samples

    def __init__(self):
        self.pa = None
        self.stream = None
        self.loopback_device_info = None
        self.device_name = "None"
        self.device_sample_rate = self.SAMPLE_RATE
        self.device_channels = self.CHANNELS
        self.engine_name = "uninitialized"
        
        self._lock = threading.Lock()
        self._cond = threading.Condition(self._lock)
        self._buffer = bytearray()
        self._is_running = False
        self._total_captured_bytes = 0
        self._last_nonzero_rms = 0.0
        self._last_packet_time = 0.0

        # Enable 1ms Windows OS multimedia timer resolution for jitter-free audio scheduling
        try:
            import ctypes
            ctypes.windll.winmm.timeBeginPeriod(1)
        except Exception:
            pass

        self._init_loopback_stream()

    def _find_wasapi_loopback_device(self):
        """Locate default speaker output device and its matching WASAPI loopback device."""
        if not _HAS_PYAUDIOWPATCH:
            raise RuntimeError("PyAudioWPatch is not installed. Install via 'pip install PyAudioWPatch'.")

        self.pa = pyaudio.PyAudio()
        try:
            wasapi_info = self.pa.get_host_api_info_by_type(pyaudio.paWASAPI)
        except Exception as e:
            raise RuntimeError(f"Windows WASAPI host API unavailable: {e}")

        default_out_idx = wasapi_info.get("defaultOutputDevice", -1)
        if default_out_idx < 0:
            raise RuntimeError("No default audio output device found for WASAPI.")

        default_speakers = self.pa.get_device_info_by_index(default_out_idx)
        speaker_name = default_speakers.get("name", "Unknown Speakers")
        print(f"[AudioCapture] Detected default Windows playback device: '{speaker_name}'")

        # Find matching loopback device for default speakers
        loopback_devices = list(self.pa.get_loopback_device_info_generator())
        matched_loopback = None
        for dev in loopback_devices:
            if speaker_name in dev["name"]:
                matched_loopback = dev
                break

        # If not exact match, use first available loopback device
        if not matched_loopback and loopback_devices:
            matched_loopback = loopback_devices[0]

        if not matched_loopback:
            raise RuntimeError(f"No WASAPI loopback device found for '{speaker_name}'.")

        return matched_loopback

    def _audio_callback(self, in_data, frame_count, time_info, status):
        """PyAudio asynchronous stream callback for incoming system audio packets."""
        if in_data and len(in_data) > 0:
            raw_chunk = in_data
            
            # If device sample rate is different from 48kHz, resample
            if self.device_sample_rate != self.SAMPLE_RATE:
                samples = np.frombuffer(raw_chunk, dtype=np.int16)
                if self.device_channels == 2:
                    samples = samples.reshape(-1, 2)
                    num_input = len(samples)
                    num_output = int(round(num_input * (self.SAMPLE_RATE / self.device_sample_rate)))
                    indices = np.linspace(0, num_input - 1, num_output)
                    resampled_l = np.interp(indices, np.arange(num_input), samples[:, 0])
                    resampled_r = np.interp(indices, np.arange(num_input), samples[:, 1])
                    samples_out = np.column_stack([resampled_l, resampled_r]).astype(np.int16)
                    raw_chunk = samples_out.tobytes()
                elif self.device_channels == 1:
                    num_input = len(samples)
                    num_output = int(round(num_input * (self.SAMPLE_RATE / self.device_sample_rate)))
                    indices = np.linspace(0, num_input - 1, num_output)
                    resampled = np.interp(indices, np.arange(num_input), samples).astype(np.int16)
                    samples_out = np.column_stack([resampled, resampled])
                    raw_chunk = samples_out.tobytes()

            with self._cond:
                self._buffer.extend(raw_chunk)
                self._total_captured_bytes += len(raw_chunk)
                self._last_packet_time = time.time()

                # Calculate RMS of non-silent chunks
                chunk_samples = np.frombuffer(raw_chunk, dtype=np.int16)
                if len(chunk_samples) > 0:
                    rms = float(np.sqrt(np.mean(chunk_samples.astype(np.float64)**2)))
                    if rms > 1.0:
                        self._last_nonzero_rms = rms

                # Cap buffer to 350ms of audio (48000 * 2 ch * 2 bytes * 0.35 = 67,200 bytes)
                # Prevents buffer starvation during network bursts while keeping latency under 350ms
                max_bytes = int(self.SAMPLE_RATE * self.CHANNELS * 2 * 0.35)
                if len(self._buffer) > max_bytes:
                    self._buffer = self._buffer[-max_bytes:]

                self._cond.notify_all()

        return (None, pyaudio.paContinue if self._is_running else pyaudio.paComplete)

    def _init_loopback_stream(self):
        """Open real WASAPI Loopback stream using PyAudioWPatch or sounddevice."""
        if _HAS_PYAUDIOWPATCH:
            try:
                loopback = self._find_wasapi_loopback_device()
                self.loopback_device_info = loopback
                self.device_name = loopback["name"]
                self.device_sample_rate = int(loopback.get("defaultSampleRate", 48000))
                self.device_channels = int(loopback.get("maxInputChannels", 2))

                print(f"[AudioCapture] Initializing WASAPI loopback: '{self.device_name}' "
                      f"(Native: {self.device_sample_rate}Hz, {self.device_channels} ch -> Target: 48kHz Stereo)")

                self.stream = self.pa.open(
                    format=pyaudio.paInt16,
                    channels=self.device_channels,
                    rate=self.device_sample_rate,
                    input=True,
                    input_device_index=loopback["index"],
                    stream_callback=self._audio_callback,
                    frames_per_buffer=self.SAMPLES_PER_FRAME
                )
                self._is_running = True
                self.stream.start_stream()
                self.engine_name = f"PyAudioWPatch WASAPI Loopback ({self.device_name})"
                print(f"[AudioCapture] [OK] WASAPI Loopback stream ACTIVE: {self.engine_name}")
                return
            except Exception as e:
                print(f"[AudioCapture] [WARN] PyAudioWPatch loopback initialization failed: {e}")

        # Fallback to sounddevice WASAPI Loopback
        if _HAS_SOUNDDEVICE:
            try:
                wasapi = None
                try:
                    wasapi = sd.WasapiSettings(loopback=True)
                except (TypeError, Exception):
                    try:
                        wasapi = sd.WasapiSettings(exclusive=False)
                    except Exception:
                        wasapi = None
                def sd_callback(indata, frames, time_info, status):
                    if indata is not None and len(indata) > 0 and self._is_running:
                        int16_samples = np.clip(indata * 32767.0, -32768, 32767).astype(np.int16)
                        raw_bytes = int16_samples.tobytes()
                        with self._cond:
                            self._buffer.extend(raw_bytes)
                            self._total_captured_bytes += len(raw_bytes)
                            self._last_packet_time = time.time()
                            rms = float(np.sqrt(np.mean(int16_samples.astype(np.float64)**2)))
                            if rms > 1.0:
                                self._last_nonzero_rms = rms
                            max_bytes = int(self.SAMPLE_RATE * self.CHANNELS * 2 * 0.10)
                            if len(self._buffer) > max_bytes:
                                self._buffer = self._buffer[-max_bytes:]
                            self._cond.notify_all()

                default_out = sd.default.device[1]
                dev_info = sd.query_devices(default_out)
                self.device_name = dev_info['name']
                self.stream = sd.InputStream(
                    device=default_out,
                    channels=self.CHANNELS,
                    samplerate=self.SAMPLE_RATE,
                    dtype='float32',
                    blocksize=self.SAMPLES_PER_FRAME,
                    extra_settings=wasapi,
                    callback=sd_callback
                )
                self.stream.start()
                self._is_running = True
                self.engine_name = f"sounddevice WASAPI Loopback ({self.device_name})"
                print(f"[AudioCapture] [OK] WASAPI Loopback stream ACTIVE via sounddevice: {self.engine_name}")
                return
            except Exception as e:
                print(f"[AudioCapture] [FAIL] sounddevice WASAPI Loopback error: {e}")

        self.engine_name = "WASAPI Loopback UNAVAILABLE"
        self.stream = None
        self._is_running = False

    def read_frame_samples(self) -> np.ndarray:
        """
        Read 960 samples (20ms) of stereo audio as int16 NumPy ndarray (960, 2).
        If audio is actively playing, returns actual captured audio.
        If system is currently silent, returns silence without stalling the event loop.
        """
        bytes_needed = self.SAMPLES_PER_FRAME * self.CHANNELS * 2  # 3840 bytes

        with self._cond:
            if len(self._buffer) >= bytes_needed:
                chunk = bytes(self._buffer[:bytes_needed])
                del self._buffer[:bytes_needed]
                return np.frombuffer(chunk, dtype=np.int16).reshape(self.SAMPLES_PER_FRAME, self.CHANNELS)

            # If audio callback has a packet in-flight, wait at most 4ms to avoid blocking asyncio
            if self._is_running:
                self._cond.wait(timeout=0.004)
                if len(self._buffer) >= bytes_needed:
                    chunk = bytes(self._buffer[:bytes_needed])
                    del self._buffer[:bytes_needed]
                    return np.frombuffer(chunk, dtype=np.int16).reshape(self.SAMPLES_PER_FRAME, self.CHANNELS)

        return np.zeros((self.SAMPLES_PER_FRAME, self.CHANNELS), dtype=np.int16)

    def read_available_samples_bytes(self, max_chunks: int = 4) -> bytes:
        """
        Read all currently available 20ms audio chunks (up to max_chunks * 3840 bytes)
        from buffer atomically without blocking. Used by remote push worker for low-latency batching.
        """
        bytes_per_chunk = self.SAMPLES_PER_FRAME * self.CHANNELS * 2  # 3840 bytes (20ms at 48kHz stereo)
        with self._cond:
            available_chunks = len(self._buffer) // bytes_per_chunk
            if available_chunks == 0:
                return b""
            chunks_to_read = min(available_chunks, max_chunks)
            total_bytes = chunks_to_read * bytes_per_chunk
            data = bytes(self._buffer[:total_bytes])
            del self._buffer[:total_bytes]
            return data

    def is_loopback_active(self) -> bool:
        """Returns True if WASAPI loopback stream is active."""
        if not self._is_running or not self.stream:
            return False
        if hasattr(self.stream, 'is_active'):
            return bool(self.stream.is_active())
        if hasattr(self.stream, 'active'):
            return bool(self.stream.active)
        return True

    def get_stats(self) -> dict:
        """Return audio capture statistics."""
        return {
            "engine": self.engine_name,
            "device": self.device_name,
            "sample_rate": self.SAMPLE_RATE,
            "channels": self.CHANNELS,
            "total_bytes_captured": self._total_captured_bytes,
            "last_nonzero_rms": self._last_nonzero_rms,
            "is_active": self.is_loopback_active(),
            "buffer_bytes_buffered": len(self._buffer)
        }

    def close(self):
        """Cleanly close and terminate WASAPI loopback stream."""
        self._is_running = False
        if self.stream:
            try:
                if hasattr(self.stream, 'stop_stream'):
                    self.stream.stop_stream()
                    self.stream.close()
                elif hasattr(self.stream, 'stop'):
                    self.stream.stop()
                    self.stream.close()
            except Exception:
                pass
            self.stream = None
        if self.pa:
            try:
                self.pa.terminate()
            except Exception:
                pass
            self.pa = None
        print("[AudioCapture] WASAPI loopback stream closed.")
