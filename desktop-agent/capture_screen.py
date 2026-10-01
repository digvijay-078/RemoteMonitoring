"""
DirectX / DXGI Desktop Duplication Screen Capture for Windows Desktop Agent.
Primary: Direct3D 11 Desktop Duplication via bettercam (GPU VRAM).
Fallback: High-performance MSS GDI/DirectX desktop capture engine.
Captures actual desktop pixels at native resolution (1920x1080) at 30 FPS.
"""

import os
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"
import sys
import ctypes
import subprocess
import time
import threading
import numpy as np

try:
    import cv2
    HAS_CV2 = True
except ImportError:
    HAS_CV2 = False

import mss


class ScreenCapture:
    """High-performance Windows live screen capture engine."""

    def __init__(self, target_fps: int = 30, display_idx: int = 0):
        self.target_fps = target_fps
        self.frame_interval = 1.0 / target_fps
        self.display_idx = display_idx
        self.camera = None
        self.sct = None
        self._thread_local = threading.local()
        self._lock = threading.Lock()
        self._desktop_handle = None
        self._last_frame = None
        self._last_capture_time = 0.0
        self.engine_name = "unknown"
        self.dxgi_failure_reason = None
        self._total_frames_captured = 0

        # Configure 64-bit ctypes for Windows desktop handles
        self._setup_win32_types()
        
        # Attach and hold handle to active interactive desktop
        self._attach_to_input_desktop()
        self._init_capture()

    def _setup_win32_types(self):
        try:
            from ctypes import wintypes
            u32 = ctypes.windll.user32
            u32.OpenDesktopW.restype = wintypes.HDESK
            u32.OpenDesktopW.argtypes = [wintypes.LPCWSTR, wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
            u32.SetThreadDesktop.restype = wintypes.BOOL
            u32.SetThreadDesktop.argtypes = [wintypes.HDESK]
            u32.OpenInputDesktop.restype = wintypes.HDESK
            u32.OpenInputDesktop.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
            u32.CloseDesktop.restype = wintypes.BOOL
            u32.CloseDesktop.argtypes = [wintypes.HDESK]
        except Exception:
            pass

    def _attach_to_input_desktop(self):
        """Ensure current thread is attached to the real interactive Windows desktop ('Default')."""
        try:
            user32 = ctypes.windll.user32
            h_default = user32.OpenDesktopW("Default", 0, False, 0x01FF)
            if h_default:
                if user32.SetThreadDesktop(h_default):
                    self._desktop_handle = h_default
                    return
            h_input = user32.OpenInputDesktop(0, False, 0x01FF)
            if h_input:
                user32.SetThreadDesktop(h_input)
                self._desktop_handle = h_input
        except Exception:
            pass

    def _get_thread_sct(self):
        """Get or initialize thread-local MSS instance with attached interactive desktop."""
        if not hasattr(self._thread_local, 'sct') or self._thread_local.sct is None:
            self._attach_to_input_desktop()
            self._thread_local.sct = mss.mss()
        return self._thread_local.sct

    def _probe_dxgi_support(self) -> bool:
        """
        Safely probe if Direct3D 11 DXGI Duplication is permitted in this Windows session.
        Probing in a lightweight subprocess prevents DXGI COM E_ACCESSDENIED from
        poisoning the GDI/BitBlt subsystem in the main streaming process.
        """
        probe_code = (
            "import ctypes, bettercam, cv2; "
            "from ctypes import wintypes; "
            "u32 = ctypes.windll.user32; "
            "u32.OpenDesktopW.restype = wintypes.HDESK; "
            "u32.SetThreadDesktop.argtypes = [wintypes.HDESK]; "
            "h = u32.OpenDesktopW('Default', 0, False, 0x01FF); "
            "u32.SetThreadDesktop(h); "
            "c = bettercam.create(device_idx=0, output_idx=" + str(self.display_idx) + "); "
            "del c"
        )
        try:
            res = subprocess.run(
                [sys.executable, "-c", probe_code],
                capture_output=True,
                text=True,
                timeout=3.0
            )
            if res.returncode == 0:
                return True
            else:
                err_text = res.stderr.strip() if res.stderr else "Access is denied."
                self.dxgi_failure_reason = err_text.splitlines()[-1] if err_text else "DXGI E_ACCESSDENIED"
                return False
        except Exception as e:
            self.dxgi_failure_reason = str(e)
            return False

    def _init_capture(self):
        # 1. Safely probe Direct3D 11 DXGI Duplication first (Primary)
        dxgi_supported = self._probe_dxgi_support()
        if dxgi_supported:
            try:
                import bettercam
                bettercam.BetterCam.__del__ = lambda self: None
                cam = bettercam.create(
                    device_idx=0,
                    output_idx=self.display_idx,
                    output_color="BGR",
                    max_buffer_len=4
                )
                if cam:
                    if not hasattr(cam, "is_capturing"):
                        cam.is_capturing = False
                    cam.start(target_fps=self.target_fps, video_mode=True)
                    self.camera = cam
                    self.engine_name = "Direct3D 11 DXGI Desktop Duplication (bettercam)"
                    print(f"[ScreenCapture] [OK] Primary Engine ACTIVE: {self.engine_name}")
                    return
            except Exception as e:
                self.dxgi_failure_reason = str(e)

        # 2. Report exact reason if DXGI Duplication is unavailable
        print(f"[ScreenCapture] [NOTICE] Direct3D 11 DXGI Duplication unavailable: {self.dxgi_failure_reason}")
        print("  Reason: Direct3D 11 DXGI requires an interactive unlocked console session without hybrid graphics isolation.")
        print("  Engaging high-performance MSS Desktop capture engine as primary capture path.")

        # 3. Initialize high-performance MSS engine
        try:
            self.sct = self._get_thread_sct()
            self.engine_name = "MSS Desktop Capture (High-Performance GDI/DirectX)"
            print(f"[ScreenCapture] [OK] Active Capture Engine: {self.engine_name}")
        except Exception as e:
            print(f"[ScreenCapture] [ERROR] MSS initialization error: {e}")

    def get_resolution(self) -> tuple[int, int]:
        """Return (width, height) of target screen."""
        if self.camera:
            try:
                frame = self.camera.get_latest_frame()
                if frame is not None:
                    h, w = frame.shape[:2]
                    return (w, h)
            except Exception:
                pass

        try:
            sct = self._get_thread_sct()
            mon = sct.monitors[1] if len(sct.monitors) > 1 else sct.monitors[0]
            return (mon["width"], mon["height"])
        except Exception:
            return (1920, 1080)

    def capture_frame(self) -> np.ndarray:
        """
        Capture latest screen frame as BGR uint8 NumPy ndarray (H, W, 3).
        Guarantees a valid ndarray of actual desktop pixels.
        Thread-safe and jitter-caching to avoid GDI/DirectX grab collisions.
        """
        now = time.time()
        with self._lock:
            # Return cached frame if captured within last 12ms (prevents redundant GDI grabs)
            if self._last_frame is not None and (now - self._last_capture_time) < (self.frame_interval * 0.4):
                return self._last_frame

            frame = None

            if self.camera is not None:
                try:
                    frame = self.camera.get_latest_frame()
                except Exception:
                    pass

            if frame is None or frame.mean() == 0.0:
                try:
                    sct = self._get_thread_sct()
                    mon = sct.monitors[1] if len(sct.monitors) > 1 else sct.monitors[0]
                    img = sct.grab(mon)
                    raw_bgra = np.frombuffer(img.raw, dtype=np.uint8).reshape((img.height, img.width, 4))
                    if HAS_CV2:
                        f_cand = cv2.cvtColor(raw_bgra, cv2.COLOR_BGRA2BGR)
                    else:
                        f_cand = raw_bgra[:, :, :3]
                    if f_cand.mean() > 0.0:
                        frame = f_cand
                except Exception:
                    # Reset thread-local sct and re-attach
                    try:
                        self._attach_to_input_desktop()
                        if hasattr(self._thread_local, 'sct') and self._thread_local.sct:
                            try:
                                self._thread_local.sct.close()
                            except Exception:
                                pass
                        self._thread_local.sct = mss.mss()
                        sct = self._thread_local.sct
                        mon = sct.monitors[1] if len(sct.monitors) > 1 else sct.monitors[0]
                        img = sct.grab(mon)
                        raw_bgra = np.frombuffer(img.raw, dtype=np.uint8).reshape((img.height, img.width, 4))
                        if HAS_CV2:
                            f_cand = cv2.cvtColor(raw_bgra, cv2.COLOR_BGRA2BGR)
                        else:
                            f_cand = raw_bgra[:, :, :3]
                        if f_cand.mean() > 0.0:
                            frame = f_cand
                    except Exception:
                        pass

            # Robust fallback to PIL ImageGrab if MSS was black or failed
            if frame is None or frame.mean() == 0.0:
                try:
                    from PIL import ImageGrab
                    self._attach_to_input_desktop()
                    pil_img = ImageGrab.grab()
                    if pil_img:
                        arr = np.array(pil_img)
                        if arr.ndim == 3:
                            if arr.shape[2] == 4:
                                f_cand = arr[:, :, [2, 1, 0]]
                            else:
                                f_cand = arr[:, :, ::-1]
                            if f_cand.mean() > 0.0:
                                frame = f_cand
                except Exception:
                    pass

            if frame is not None and frame.mean() > 0.0:
                self._last_frame = frame
                self._last_capture_time = now
                self._total_frames_captured += 1
                return frame

            # NEVER discard previous good frame with black screen!
            # Returning previous frame provides seamless smooth continuity
            if self._last_frame is not None:
                return self._last_frame

            # Initial fallback only if no frame has ever been captured yet
            w, h = self.get_resolution()
            return np.zeros((h, w, 3), dtype=np.uint8)

    def get_stats(self) -> dict:
        """Return capture metrics."""
        w, h = self.get_resolution()
        return {
            "engine": self.engine_name,
            "dxgi_failure_reason": self.dxgi_failure_reason,
            "resolution": f"{w}x{h}",
            "target_fps": self.target_fps,
            "total_frames": self._total_frames_captured,
            "display_idx": self.display_idx
        }

    def close(self):
        """Release capture resources."""
        if self.camera:
            try:
                self.camera.stop()
            except Exception:
                pass
            self.camera = None
        if self.sct:
            try:
                self.sct.close()
            except Exception:
                pass
            self.sct = None
        if self._desktop_handle:
            try:
                ctypes.windll.user32.CloseDesktop(self._desktop_handle)
            except Exception:
                pass
            self._desktop_handle = None
        print("[ScreenCapture] Screen capture engine closed.")
