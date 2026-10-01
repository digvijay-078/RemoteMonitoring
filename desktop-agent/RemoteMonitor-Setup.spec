# -*- mode: python ; coding: utf-8 -*-
import sys
import os
from PyInstaller.utils.hooks import collect_all, collect_submodules

block_cipher = None

datas = []
binaries = []
hiddenimports = [
    'mss',
    'mss.windows',
    'PIL',
    'PIL.Image',
    'requests',
    'urllib3',
    'websockets',
    'psutil',
    'pyaudiowpatch',
    'numpy',
    'cv2',
    'aiortc',
    'av',
    'bettercam',
    'win32api',
    'win32con',
    'win32gui',
    'winreg',
    'tkinter',
    'tkinter.ttk',
    'tkinter.messagebox',
    'crypto_storage',
    'stream_manager',
    'reverb_signalling',
    'capture_screen',
    'capture_audio',
    'webrtc_tracks',
    'mic_player',
    'http_streamer'
]

for mod in ['numpy', 'cv2', 'pyaudiowpatch', 'mss', 'aiortc', 'av', 'websockets', 'PIL', 'requests']:
    try:
        tmp_ret = collect_all(mod)
        datas += tmp_ret[0]
        binaries += tmp_ret[1]
        hiddenimports += tmp_ret[2]
    except Exception:
        pass

a = Analysis(
    ['client_gui_setup.py'],
    pathex=['c:/RemoteMonitoring/desktop-agent'],
    binaries=binaries,
    datas=datas,
    hiddenimports=hiddenimports,
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=['matplotlib', 'scipy', 'pandas', 'IPython', 'notebook'],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=block_cipher,
    noarchive=False,
    optimize=0,
)

pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='RemoteMonitor-Setup',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=False,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon=None
)
