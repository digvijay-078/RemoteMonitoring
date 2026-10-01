"""
WebRTC MediaStreamTracks for Video (Screen) and Audio (System WASAPI).
Provides continuous live video and audio frame generator coroutines for aiortc.
"""

import asyncio
import time
from fractions import Fraction
import av
from aiortc import VideoStreamTrack, AudioStreamTrack

from capture_screen import ScreenCapture
from capture_audio import AudioCapture


class ScreenVideoTrack(VideoStreamTrack):
    """WebRTC Video Stream Track streaming live Windows Desktop Screen."""

    kind = "video"

    def __init__(self, capture: ScreenCapture, fps: int = 30):
        super().__init__()
        self.capture = capture
        self.fps = fps
        self.interval = 1.0 / fps
        self.time_base = Fraction(1, 90000) # WebRTC standard 90kHz video clock
        self._start_time = None
        self._frame_count = 0

    async def recv(self) -> av.VideoFrame:
        if self._start_time is None:
            self._start_time = time.time()

        target_time = self._start_time + (self._frame_count * self.interval)
        delay = target_time - time.time()
        if delay > 0:
            await asyncio.sleep(delay)
        elif delay < -0.10:
            # Re-align start time if system fell behind to prevent frame capture burst storms
            self._start_time = time.time() - (self._frame_count * self.interval)

        # Capture live desktop frame (thread-safe and jitter-cached)
        bgr_frame = self.capture.capture_frame()

        # Convert numpy BGR ndarray to PyAV VideoFrame
        video_frame = av.VideoFrame.from_ndarray(bgr_frame, format="bgr24")
        
        # Compute presentation timestamp (PTS)
        pts = int(self._frame_count * (90000 / self.fps))
        video_frame.pts = pts
        video_frame.time_base = self.time_base

        self._frame_count += 1
        return video_frame


class SystemAudioTrack(AudioStreamTrack):
    """WebRTC Audio Stream Track streaming live Windows System Audio (WASAPI)."""

    kind = "audio"

    def __init__(self, capture: AudioCapture):
        super().__init__()
        self.capture = capture
        self.sample_rate = AudioCapture.SAMPLE_RATE # 48000
        self.samples_per_frame = AudioCapture.SAMPLES_PER_FRAME # 960
        self.interval = AudioCapture.FRAME_DURATION_MS / 1000.0 # 0.02s (20ms)
        self.time_base = Fraction(1, self.sample_rate)
        self._start_time = None
        self._frame_count = 0

    async def recv(self) -> av.AudioFrame:
        if self._start_time is None:
            self._start_time = time.time()

        target_time = self._start_time + (self._frame_count * self.interval)
        delay = target_time - time.time()
        if delay > 0:
            await asyncio.sleep(delay)
        elif delay < -0.06:
            # Re-align clock if fell behind by >3 audio frames to prevent buffer starvation
            self._start_time = time.time() - (self._frame_count * self.interval)

        # Read 960 stereo samples
        samples = self.capture.read_frame_samples() # Shape: (960, 2), dtype int16

        # PyAV AudioFrame expects shape (1, samples * channels) for packed s16 format
        audio_frame = av.AudioFrame.from_ndarray(samples.reshape(1, -1), format="s16", layout="stereo")
        audio_frame.sample_rate = self.sample_rate

        # Compute PTS in sample clock units
        pts = self._frame_count * self.samples_per_frame
        audio_frame.pts = pts
        audio_frame.time_base = self.time_base

        self._frame_count += 1
        return audio_frame
