"""
Windows DPAPI Credential Storage for RemoteMonitor Desktop Agent.
Uses native CryptProtectData / CryptUnprotectData via ctypes to securely
encrypt the permanent 256-bit device token to the Windows user context.
"""

import os
import sys
import json
import ctypes
from ctypes import wintypes
from pathlib import Path

# DPAPI Structures
class DATA_BLOB(ctypes.Structure):
    _fields_ = [
        ("cbData", wintypes.DWORD),
        ("pbData", ctypes.POINTER(ctypes.c_byte)),
    ]

CryptProtectData = ctypes.windll.crypt32.CryptProtectData
CryptProtectData.argtypes = [
    ctypes.POINTER(DATA_BLOB), # pDataIn
    wintypes.LPCWSTR,          # szDataDescr
    ctypes.POINTER(DATA_BLOB), # pOptionalEntropy
    ctypes.c_void_p,           # pvReserved
    ctypes.c_void_p,           # pPromptStruct
    wintypes.DWORD,            # dwFlags
    ctypes.POINTER(DATA_BLOB), # pDataOut
]
CryptProtectData.restype = wintypes.BOOL

CryptUnprotectData = ctypes.windll.crypt32.CryptUnprotectData
CryptUnprotectData.argtypes = [
    ctypes.POINTER(DATA_BLOB), # pDataIn
    ctypes.POINTER(wintypes.LPWSTR), # ppszDataDescr
    ctypes.POINTER(DATA_BLOB), # pOptionalEntropy
    ctypes.c_void_p,           # pvReserved
    ctypes.c_void_p,           # pPromptStruct
    wintypes.DWORD,            # dwFlags
    ctypes.POINTER(DATA_BLOB), # pDataOut
]
CryptUnprotectData.restype = wintypes.BOOL

LocalFree = ctypes.windll.kernel32.LocalFree
LocalFree.argtypes = [ctypes.c_void_p]
LocalFree.restype = ctypes.c_void_p

CRYPTPROTECT_UI_FORBIDDEN = 0x1


def _encrypt_bytes(data: bytes, description: str = "RemoteMonitor Token") -> bytes:
    """Encrypt raw bytes using DPAPI tied to current Windows user."""
    in_blob = DATA_BLOB(len(data), ctypes.cast(ctypes.create_string_buffer(data, len(data)), ctypes.POINTER(ctypes.c_byte)))
    out_blob = DATA_BLOB()

    if not CryptProtectData(
        ctypes.byref(in_blob),
        description,
        None,
        None,
        None,
        CRYPTPROTECT_UI_FORBIDDEN,
        ctypes.byref(out_blob)
    ):
        raise ctypes.WinError()

    try:
        encrypted = ctypes.string_at(out_blob.pbData, out_blob.cbData)
        return encrypted
    finally:
        LocalFree(out_blob.pbData)


def _decrypt_bytes(encrypted: bytes) -> bytes:
    """Decrypt DPAPI-encrypted bytes for current Windows user."""
    in_blob = DATA_BLOB(len(encrypted), ctypes.cast(ctypes.create_string_buffer(encrypted, len(encrypted)), ctypes.POINTER(ctypes.c_byte)))
    out_blob = DATA_BLOB()

    if not CryptUnprotectData(
        ctypes.byref(in_blob),
        None,
        None,
        None,
        None,
        CRYPTPROTECT_UI_FORBIDDEN,
        ctypes.byref(out_blob)
    ):
        raise ctypes.WinError()

    try:
        decrypted = ctypes.string_at(out_blob.pbData, out_blob.cbData)
        return decrypted
    finally:
        LocalFree(out_blob.pbData)


class AgentCredentialStore:
    """Manages secure persistence of Desktop Agent credentials."""

    def __init__(self, storage_dir: Path | None = None):
        if storage_dir is None:
            appdata = os.environ.get("APPDATA") or str(Path.home())
            self.storage_dir = Path(appdata) / "RemoteMonitor"
        else:
            self.storage_dir = Path(storage_dir)

        self.storage_dir.mkdir(parents=True, exist_ok=True)
        self.credential_file = self.storage_dir / "desktop_credential.dat"

    def has_credentials(self) -> bool:
        return self.credential_file.exists() and self.credential_file.stat().st_size > 0

    def save_credentials(self, data: dict) -> None:
        """Encrypt and save credential dictionary."""
        json_str = json.dumps(data)
        encrypted = _encrypt_bytes(json_str.encode("utf-8"))
        with open(self.credential_file, "wb") as f:
            f.write(encrypted)

    def load_credentials(self) -> dict | None:
        """Load and decrypt credential dictionary."""
        if not self.has_credentials():
            return None
        try:
            with open(self.credential_file, "rb") as f:
                encrypted = f.read()
            decrypted = _decrypt_bytes(encrypted)
            return json.loads(decrypted.decode("utf-8"))
        except Exception as e:
            print(f"[CryptoStore] Error decrypting credentials: {e}", file=sys.stderr)
            return None

    def clear_credentials(self) -> None:
        """Remove stored credentials file."""
        if self.credential_file.exists():
            self.credential_file.unlink()
