"""
RemoteMonitor Desktop Agent - 1-Click GUI Setup & Zero-Touch Streaming Controller.
Can run as a standalone Windows .exe application.
"""

import os
import sys
import time
import socket
import getpass
import platform
import threading
import asyncio
import argparse
import tkinter as tk
from tkinter import ttk, messagebox

# Ensure desktop-agent directory is in sys.path
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

# Set OpenBLAS/MKL threads to 1 before numpy
os.environ["OPENBLAS_NUM_THREADS"] = "1"
os.environ["MKL_NUM_THREADS"] = "1"
os.environ["OMP_NUM_THREADS"] = "1"

import requests
from crypto_storage import AgentCredentialStore
from stream_manager import StreamManager
from reverb_signalling import ReverbSignallingClient

DEFAULT_SERVER = "https://scrambler-unmixable-curve.ngrok-free.dev"
LOCAL_FALLBACK_SERVER = "http://127.0.0.1:8088"

def get_machine_guid() -> str:
    """Read Windows MachineGuid from registry or generate consistent fallback."""
    try:
        import winreg
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


class AgentService:
    def __init__(self):
        self.stream_manager = None
        self.signalling = None
        self.is_running = False
        self.thread = None
        self.loop = None
        self.store = AgentCredentialStore()
        self.active_identifier = None

    def verify_token(self, server_url: str, token: str) -> tuple[bool, dict]:
        """Check if stored device token is valid and active on server."""
        try:
            res = requests.get(
                f"{server_url.rstrip('/')}/api/v1/device/me",
                headers={
                    "Authorization": f"Bearer {token}",
                    "ngrok-skip-browser-warning": "1",
                    "User-Agent": "RemoteMonitorAgent/2.0"
                },
                timeout=6
            )
            if res.status_code == 200:
                data = res.json()
                return True, data.get("device", {})
        except Exception:
            pass
        return False, {}

    def auto_register(self, server_url: str) -> tuple[bool, dict]:
        """Zero-Touch Auto-Enrollment: Registers PC with Admin Portal automatically."""
        hostname = socket.gethostname()
        username = getpass.getuser()
        machine_id = get_machine_guid()

        payload = {
            "hostname": hostname,
            "username": username,
            "machine_id": machine_id,
            "device_type": "desktop",
            "screen_resolution": "1920x1080",
            "fps": 30,
            "hardware_info": {
                "os": platform.platform(),
                "processor": platform.processor(),
                "machine_id": machine_id,
                "auto_enrolled": True,
            },
            "user_agent": "RemoteMonitor-Windows-Setup-Exe/2.0",
        }

        clean_server = server_url.rstrip("/")
        endpoints = ["/api/v1/device/auto-register", "/api/v1/devices/auto-register"]

        headers = {
            "ngrok-skip-browser-warning": "1",
            "User-Agent": "RemoteMonitorAgent/2.0"
        }

        for ep in endpoints:
            try:
                res = requests.post(
                    f"{clean_server}{ep}",
                    json=payload,
                    headers=headers,
                    timeout=15,
                )
                if res.status_code == 200:
                    data = res.json()
                    device_info = data.get("device", {})
                    token = data.get("token") or data.get("device_token")
                    
                    parsed = requests.utils.urlparse(clean_server)
                    if clean_server.startswith("https://"):
                        ws_url = f"wss://{parsed.hostname}"
                    else:
                        ws_url = f"ws://{parsed.hostname or '127.0.0.1'}:8080"

                    self.store.save_credentials({
                        "device_token": token,
                        "device_identifier": device_info.get("identifier") or device_info.get("device_identifier"),
                        "device_name": device_info.get("name"),
                        "device_uuid": device_info.get("uuid"),
                        "server_url": clean_server,
                        "ws_url": ws_url,
                        "paired_at": time.time(),
                    })
                    self.active_identifier = device_info.get("identifier") or device_info.get("device_identifier")
                    return True, device_info
            except Exception:
                continue

        return False, {}

    def pair_with_code(self, server_url: str, code: str) -> bool:
        """Manual pairing using code if provided."""
        clean_server = server_url.rstrip("/")
        try:
            res = requests.post(
                f"{clean_server}/api/v1/devices/pair",
                json={
                    "pairing_code": code.strip(),
                    "device_type": "desktop",
                    "screen_resolution": "1920x1080",
                    "fps": 30,
                    "ip_address": "127.0.0.1",
                    "user_agent": "RemoteMonitor-Windows-Setup-Exe/2.0",
                },
                headers={
                    "ngrok-skip-browser-warning": "1",
                    "User-Agent": "RemoteMonitorAgent/2.0"
                },
                timeout=12,
            )
            if res.status_code == 200:
                data = res.json()
                device_info = data.get("device", {})
                parsed = requests.utils.urlparse(clean_server)
                ws_url = f"wss://{parsed.hostname}" if clean_server.startswith("https://") else f"ws://{parsed.hostname or '127.0.0.1'}:8080"
                self.store.save_credentials({
                    "device_token": data.get("token"),
                    "device_identifier": device_info.get("identifier") or device_info.get("device_identifier"),
                    "device_name": device_info.get("name"),
                    "device_uuid": device_info.get("uuid"),
                    "server_url": clean_server,
                    "ws_url": ws_url,
                    "paired_at": time.time(),
                })
                self.active_identifier = device_info.get("identifier") or device_info.get("device_identifier")
                return True
            return False
        except Exception:
            return False

    def start_streaming(self, server_url: str, code: str = "", force_re_register: bool = False, on_status_cb=None):
        if self.is_running:
            return True

        clean_server = server_url.rstrip("/")
        cred = self.store.load_credentials()

        is_valid = False
        if cred and not force_re_register and cred.get("device_token") and cred.get("server_url") == clean_server:
            # Check if token is actually valid on server
            if on_status_cb:
                on_status_cb("Verifying connection with server...")
            is_valid, server_dev = self.verify_token(clean_server, cred["device_token"])
            if is_valid:
                self.active_identifier = server_dev.get("identifier") or cred.get("device_identifier")

        code_clean = code.strip() if code else ""
        has_manual_code = bool(code_clean and code_clean.upper() not in ["AUTO", "NONE", ""])

        if not is_valid or has_manual_code or force_re_register:
            if on_status_cb:
                on_status_cb("Auto-Registering with Admin Portal...")

            if has_manual_code:
                success = self.pair_with_code(clean_server, code_clean)
            else:
                success, dev_info = self.auto_register(clean_server)

            if not success and clean_server != LOCAL_FALLBACK_SERVER:
                if on_status_cb:
                    on_status_cb("Retrying auto-registration via gateway...")
                success, dev_info = self.auto_register(LOCAL_FALLBACK_SERVER)
                if success:
                    clean_server = LOCAL_FALLBACK_SERVER

            if not success:
                if on_status_cb:
                    on_status_cb("Auto-Enrollment failed. Please check internet/server URL.")
                return False

        cred = self.store.load_credentials()
        device_token = cred.get("device_token")
        device_uuid = cred.get("device_uuid")
        self.active_identifier = cred.get("device_identifier", "DESK-PC")
        ws_url = cred.get("ws_url")
        app_key = os.getenv("REVERB_APP_KEY", "nw2zhrpowiazy7xm9esc")

        self.is_running = True
        self.stream_manager = StreamManager(clean_server, device_token, device_uuid=device_uuid, fps=30)
        self.signalling = ReverbSignallingClient(
            server_url=clean_server,
            ws_url=ws_url,
            app_key=app_key,
            device_uuid=device_uuid,
            device_token=device_token,
            on_event_callback=self.stream_manager.handle_signalling_event
        )

        def heartbeat_worker():
            while self.is_running:
                try:
                    requests.post(
                        f"{clean_server}/api/v1/device/heartbeat",
                        json={"timestamp": int(time.time()), "token": device_token},
                        headers={
                            "Authorization": f"Bearer {device_token}",
                            "Content-Type": "application/json",
                            "ngrok-skip-browser-warning": "1",
                            "User-Agent": "RemoteMonitorAgent/2.0"
                        },
                        timeout=3
                    )
                except Exception:
                    pass
                time.sleep(2.5)

        self._hb_thread = threading.Thread(target=heartbeat_worker, daemon=True)
        self._hb_thread.start()

        def runner():
            self.loop = asyncio.new_event_loop()
            asyncio.set_event_loop(self.loop)
            try:
                if on_status_cb:
                    on_status_cb(f"LIVE 30 FPS - Registered as [{self.active_identifier}]")
                self.loop.run_until_complete(self.signalling.run())
            except Exception:
                pass
            finally:
                self.is_running = False
                if on_status_cb:
                    on_status_cb("Streaming Stopped")

        self.thread = threading.Thread(target=runner, daemon=True)
        self.thread.start()
        return True

    def stop_streaming(self):
        self.is_running = False
        # Send instant disconnect signal to server
        cred = self.store.load_credentials()
        if cred and cred.get("device_token") and cred.get("server_url"):
            try:
                requests.post(
                    f"{cred['server_url']}/api/v1/device/disconnect",
                    json={"reason": "user_stopped", "token": cred["device_token"]},
                    headers={
                        "Authorization": f"Bearer {cred['device_token']}",
                        "Content-Type": "application/json",
                        "ngrok-skip-browser-warning": "1",
                        "User-Agent": "RemoteMonitorAgent/2.0"
                    },
                    timeout=2.5
                )
            except Exception:
                pass

        if self.signalling:
            try:
                self.signalling.stop()
            except Exception:
                pass
        if self.stream_manager:
            try:
                self.stream_manager.http_streaming_active = False
                if hasattr(self.stream_manager, 'http_server') and self.stream_manager.http_server:
                    self.stream_manager.http_server.shutdown()
            except Exception:
                pass
        if self.loop and self.loop.is_running():
            self.loop.call_soon_threadsafe(self.loop.stop)


def install_autostart(exe_path: str):
    success = False
    # 1. Windows Registry Run Key
    try:
        import winreg
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, r"Software\Microsoft\Windows\CurrentVersion\Run", 0, winreg.KEY_SET_VALUE)
        winreg.SetValueEx(key, "RemoteMonitorDesktopAgent", 0, winreg.REG_SZ, f'"{exe_path}" --silent')
        winreg.CloseKey(key)
        success = True
    except Exception:
        pass

    # 2. Windows Task Scheduler (Highest Reliability for background logon)
    try:
        import subprocess
        subprocess.run(
            ["schtasks", "/Create", "/TN", "RemoteMonitorDesktopAgent", "/TR", f'"{exe_path}" --silent', "/SC", "ONLOGON", "/RL", "HIGHEST", "/F"],
            creationflags=0x08000000 if os.name == 'nt' else 0,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )
    except Exception:
        pass

    # 3. Clean any legacy broken VBS in Startup Folder
    try:
        startup_dir = os.path.join(os.environ.get("APPDATA", ""), r"Microsoft\Windows\Start Menu\Programs\Startup")
        legacy_vbs = os.path.join(startup_dir, "RemoteMonitorAgent.vbs")
        if os.path.exists(legacy_vbs):
            os.remove(legacy_vbs)
    except Exception:
        pass

    return success


def uninstall_autostart():
    # 1. Registry
    try:
        import winreg
        key = winreg.OpenKey(winreg.HKEY_CURRENT_USER, r"Software\Microsoft\Windows\CurrentVersion\Run", 0, winreg.KEY_SET_VALUE)
        winreg.DeleteValue(key, "RemoteMonitorDesktopAgent")
        winreg.CloseKey(key)
    except Exception:
        pass

    # 2. Task Scheduler
    try:
        import subprocess
        subprocess.run(
            ["schtasks", "/Delete", "/TN", "RemoteMonitorDesktopAgent", "/F"],
            creationflags=0x08000000 if os.name == 'nt' else 0,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )
    except Exception:
        pass

    # 3. Startup Folder
    try:
        startup_dir = os.path.join(os.environ.get("APPDATA", ""), r"Microsoft\Windows\Start Menu\Programs\Startup")
        legacy_vbs = os.path.join(startup_dir, "RemoteMonitorAgent.vbs")
        if os.path.exists(legacy_vbs):
            os.remove(legacy_vbs)
    except Exception:
        pass

    return True


class SetupApp:
    def __init__(self, root: tk.Tk):
        self.root = root
        self.root.title("RemoteMonitor - Zero-Touch Desktop Agent")
        self.root.geometry("560x550")
        self.root.resizable(False, False)
        self.root.configure(bg="#0f172a")
        self.root.protocol("WM_DELETE_WINDOW", self.on_window_close)

        self.service = AgentService()
        self.hostname = socket.gethostname()
        self.username = getpass.getuser()

        # Styles
        style = ttk.Style()
        style.theme_use('clam')
        style.configure("TLabel", background="#0f172a", foreground="#f8fafc", font=("Segoe UI", 10))

        # Top Header
        header_frame = tk.Frame(root, bg="#1e1b4b", padx=18, pady=14)
        header_frame.pack(fill="x")

        lbl_title = tk.Label(header_frame, text="RemoteMonitor Desktop Agent", font=("Segoe UI", 15, "bold"), bg="#1e1b4b", fg="#ffffff")
        lbl_title.pack(anchor="w")
        lbl_subtitle = tk.Label(header_frame, text="Zero-Touch Auto-Enrollment & 30 FPS Screen Broadcaster", font=("Segoe UI", 9), bg="#1e1b4b", fg="#a5b4fc")
        lbl_subtitle.pack(anchor="w")

        # Content Frame
        content = tk.Frame(root, bg="#0f172a", padx=20, pady=14)
        content.pack(fill="both", expand=True)

        # Workstation Detection Box
        info_card = tk.Frame(content, bg="#1e293b", padx=12, pady=10, highlightthickness=1, highlightbackground="#334155")
        info_card.pack(fill="x", pady=(0, 12))

        tk.Label(info_card, text=f"🖥️ Detected Workstation: {self.hostname}", font=("Segoe UI", 10, "bold"), bg="#1e293b", fg="#38bdf8").pack(anchor="w")
        tk.Label(info_card, text=f"👤 Logged-in User: {self.username}  |  ⚡ Auto-Registration: Enabled", font=("Segoe UI", 8), bg="#1e293b", fg="#94a3b8").pack(anchor="w", pady=(2, 0))

        # Stored / Default Server URL
        initial_server = DEFAULT_SERVER
        cred = self.service.store.load_credentials()
        if cred and cred.get("server_url"):
            saved_srv = cred.get("server_url")
            if "trycloudflare" in saved_srv or not saved_srv.startswith("https://"):
                self.service.store.clear_credentials()
                initial_server = DEFAULT_SERVER
            else:
                initial_server = saved_srv

        # Server URL
        tk.Label(content, text="Admin Portal / Cloud Server URL:", bg="#0f172a", fg="#94a3b8", font=("Segoe UI", 9, "bold")).pack(anchor="w")
        self.entry_server = tk.Entry(content, bg="#1e293b", fg="#ffffff", insertbackground="#ffffff", font=("Segoe UI", 10), relief="flat", highlightthickness=1, highlightbackground="#334155")
        self.entry_server.pack(fill="x", pady=(2, 10), ipady=5)
        self.entry_server.insert(0, initial_server)

        # Optional Pairing Code
        tk.Label(content, text="Manual Pairing Code (Optional - leave blank for Auto-Registration):", bg="#0f172a", fg="#64748b", font=("Segoe UI", 8)).pack(anchor="w")
        self.entry_code = tk.Entry(content, bg="#1e293b", fg="#cbd5e1", insertbackground="#ffffff", font=("Segoe UI", 9), relief="flat", highlightthickness=1, highlightbackground="#334155")
        self.entry_code.pack(fill="x", pady=(2, 10), ipady=4)
        self.entry_code.insert(0, "")

        # Autostart Checkbox
        self.var_autostart = tk.BooleanVar(value=True)
        chk_autostart = tk.Checkbutton(
            content,
            text="Start automatically on Windows boot (Stealth Background Mode)",
            variable=self.var_autostart,
            bg="#0f172a",
            fg="#cbd5e1",
            selectcolor="#1e293b",
            activebackground="#0f172a",
            font=("Segoe UI", 9)
        )
        chk_autostart.pack(anchor="w", pady=(0, 10))

        # Status Bar Box
        self.status_box = tk.Label(
            content,
            text="⚪ Ready - Click 'Start' to Auto-Register & Broadcast to Admin",
            bg="#1e293b",
            fg="#94a3b8",
            font=("Segoe UI", 10, "bold"),
            relief="flat",
            padx=12,
            pady=8
        )
        self.status_box.pack(fill="x", pady=(0, 10))

        # Action Buttons Frame
        btn_frame = tk.Frame(content, bg="#0f172a")
        btn_frame.pack(fill="x", pady=(0, 8))

        self.btn_start = tk.Button(
            btn_frame,
            text="🚀 Start Live Broadcast (Auto-Register)",
            bg="#4f46e5",
            fg="#ffffff",
            activebackground="#4338ca",
            activeforeground="#ffffff",
            font=("Segoe UI", 11, "bold"),
            relief="flat",
            cursor="hand2",
            command=self.on_start_clicked
        )
        self.btn_start.pack(side="left", fill="x", expand=True, ipady=8, padx=(0, 6))

        self.btn_stop = tk.Button(
            btn_frame,
            text="⏹ Stop",
            bg="#334155",
            fg="#94a3b8",
            activebackground="#1e293b",
            font=("Segoe UI", 11, "bold"),
            relief="flat",
            cursor="hand2",
            state="disabled",
            command=self.on_stop_clicked
        )
        self.btn_stop.pack(side="right", fill="x", expand=True, ipady=8, padx=(6, 0))

        # Secondary Actions
        sub_btn_frame = tk.Frame(content, bg="#0f172a")
        sub_btn_frame.pack(fill="x", pady=(0, 5))

        self.btn_stealth = tk.Button(
            sub_btn_frame,
            text="🕶️ Invisible Stealth Mode",
            bg="#1e293b",
            fg="#cbd5e1",
            activebackground="#334155",
            font=("Segoe UI", 9),
            relief="flat",
            cursor="hand2",
            command=self.on_stealth_clicked
        )
        self.btn_stealth.pack(side="left", fill="x", expand=True, ipady=4, padx=(0, 4))

        self.btn_reset = tk.Button(
            sub_btn_frame,
            text="🔄 Reset / Re-Register Device",
            bg="#1e293b",
            fg="#38bdf8",
            activebackground="#334155",
            font=("Segoe UI", 9, "bold"),
            relief="flat",
            cursor="hand2",
            command=self.on_reset_clicked
        )
        self.btn_reset.pack(side="right", fill="x", expand=True, ipady=4, padx=(4, 0))

        # Footer instructions
        lbl_footer = tk.Label(
            root,
            text="📌 Admin Workflow: Go to /admin/mappings to link this PC to any Tablet with 1-click",
            bg="#0f172a",
            fg="#38bdf8",
            font=("Segoe UI", 8, "bold")
        )
        lbl_footer.pack(side="bottom", pady=6)

    def update_status(self, text: str):
        def cb():
            if "LIVE" in text:
                self.status_box.config(text=f"🟢 {text}", fg="#34d399", bg="#064e3b")
                self.btn_start.config(state="disabled", bg="#312e81")
                self.btn_stop.config(state="normal", bg="#e11d48", fg="#ffffff")
            elif "Stopped" in text or "Ready" in text:
                self.status_box.config(text=f"⚪ {text}", fg="#94a3b8", bg="#1e293b")
                self.btn_start.config(state="normal", bg="#4f46e5", fg="#ffffff")
                self.btn_stop.config(state="disabled", bg="#334155", fg="#94a3b8")
            else:
                self.status_box.config(text=f"⏳ {text}", fg="#fbbf24", bg="#451a03")
        self.root.after(0, cb)

    def on_start_clicked(self):
        server = self.entry_server.get().strip()
        code = self.entry_code.get().strip()
        if not server:
            messagebox.showerror("Validation Error", "Please enter Cloud Server URL.")
            return

        if self.var_autostart.get():
            install_autostart(sys.executable)

        self.update_status("Auto-Registering with Admin Portal...")
        self.service.start_streaming(server, code=code, on_status_cb=self.update_status)

    def on_reset_clicked(self):
        server = self.entry_server.get().strip() or DEFAULT_SERVER
        self.service.stop_streaming()
        self.service.store.clear_credentials()
        self.update_status("Auto-Registering Fresh Device ID...")
        ok, dev = self.service.auto_register(server)
        if ok:
            dev_id = dev.get("identifier", "NEW")
            self.update_status(f"Registered fresh as [{dev_id}]")
            messagebox.showinfo("Success", f"Workstation successfully registered fresh as [{dev_id}]!\nVisible in Admin Portal now.")
            self.service.start_streaming(server, on_status_cb=self.update_status)
        else:
            messagebox.showerror("Registration Error", "Could not register with server. Please check internet.")

    def on_stop_clicked(self):
        self.service.stop_streaming()
        self.update_status("Streaming Stopped")

    def on_stealth_clicked(self):
        if not self.service.is_running:
            self.on_start_clicked()
            time.sleep(0.5)

        messagebox.showinfo(
            "Invisible Stealth Mode Activated",
            f"RemoteMonitor is now running quietly in the background.\n\n"
            f"Workstation: {self.hostname}\n"
            f"Status: Live 30 FPS Broadcasting Active\n\n"
            f"This window will now be hidden from the desktop and taskbar.\n"
            f"Broadcasting to Admin Control Room continues without interruption."
        )
        # Withdraw the window: 100% hidden from screen & taskbar, process stays alive!
        self.root.withdraw()

    def on_window_close(self):
        if self.service.is_running:
            res = messagebox.askyesno(
                "Run in Background?",
                "Live broadcasting is currently active.\n\n"
                "Do you want to keep monitoring active in the background?\n\n"
                "• Click YES to run invisibly in background.\n"
                "• Click NO to stop streaming and completely exit.",
                default="yes"
            )
            if res:
                self.root.withdraw()
            else:
                self.service.stop_streaming()
                self.root.destroy()
                sys.exit(0)
        else:
            self.root.destroy()
            sys.exit(0)


def stop_all_running_instances() -> int:
    """Find and terminate any other running instances of RemoteMonitor."""
    import psutil
    current_pid = os.getpid()
    count = 0
    for p in psutil.process_iter(['pid', 'name', 'cmdline']):
        try:
            if p.pid != current_pid and (
                (p.name() and "RemoteMonitor" in p.name()) or
                (p.name() and "python" in p.name() and any("client_gui_setup" in c for c in (p.cmdline() or [])))
            ):
                p.terminate()
                count += 1
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            pass
    return count


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--silent", action="store_true", help="Run headlessly in background with auto-registration")
    parser.add_argument("--server", default=DEFAULT_SERVER)
    parser.add_argument("--code", default="")
    parser.add_argument("--stop", action="store_true", help="Stop any running RemoteMonitor background instances")
    args = parser.parse_args()

    if args.stop:
        count = stop_all_running_instances()
        print(f"Stopped {count} running RemoteMonitor background instance(s).")
        return

    # If launched normally and an instance is already running in background:
    if not args.silent:
        import psutil
        current_pid = os.getpid()
        other_instances = []
        for p in psutil.process_iter(['pid', 'name', 'cmdline']):
            try:
                if p.pid != current_pid and (
                    (p.name() and "RemoteMonitor" in p.name()) or
                    (p.name() and "python" in p.name() and any("client_gui_setup" in c for c in (p.cmdline() or [])))
                ):
                    other_instances.append(p)
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass

        if other_instances:
            root = tk.Tk()
            root.withdraw()
            res = messagebox.askyesno(
                "RemoteMonitor Background Service",
                "RemoteMonitor is ALREADY RUNNING invisibly in the background.\n\n"
                "Do you want to STOP the background monitoring service now?\n\n"
                "• Click YES to STOP monitoring.\n"
                "• Click NO to open the Control Panel.",
                default="no"
            )
            if res:
                for p in other_instances:
                    try:
                        p.terminate()
                    except Exception:
                        pass
                messagebox.showinfo("Stopped", "RemoteMonitor background service has been stopped successfully.")
                root.destroy()
                return
            root.destroy()

    if args.silent:
        service = AgentService()
        import atexit
        import signal
        atexit.register(service.stop_streaming)

        def handle_sig(sig, frame):
            service.stop_streaming()
            sys.exit(0)

        try:
            signal.signal(signal.SIGINT, handle_sig)
            signal.signal(signal.SIGTERM, handle_sig)
        except Exception:
            pass

        service.start_streaming(args.server, code=args.code)
        try:
            while True:
                time.sleep(1)
        except (KeyboardInterrupt, SystemExit):
            service.stop_streaming()
    else:
        root = tk.Tk()
        app = SetupApp(root)
        import atexit
        atexit.register(app.service.stop_streaming)
        root.mainloop()


if __name__ == "__main__":
    main()
