import os
import zipfile
from pathlib import Path

BASE_DIR = Path("C:/RemoteMonitoring")
AGENT_DIR = BASE_DIR / "desktop-agent"
PUBLIC_DOWNLOADS = BASE_DIR / "public" / "downloads"

PUBLIC_DOWNLOADS.mkdir(parents=True, exist_ok=True)

zip_targets = [
    PUBLIC_DOWNLOADS / "RemoteMonitor-Agent.zip",
    PUBLIC_DOWNLOADS / "RemoteMonitor-ClientSetup.zip",
]

# Files to exclude
EXCLUDE_EXTS = {".dat", ".pyc", ".spec", ".log", ".tmp"}
EXCLUDE_DIRS = {"__pycache__", "build", "dist", "logs", ".git"}

files_to_pack = []
for root, dirs, files in os.walk(AGENT_DIR):
    # prune excluded dirs
    dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
    for f in files:
        ext = os.path.splitext(f)[1].lower()
        if ext in EXCLUDE_EXTS:
            continue
        if f.endswith(".exe"):  # Don't pack 100MB exe inside the small zip
            continue
        rel_path = os.path.relpath(os.path.join(root, f), AGENT_DIR)
        files_to_pack.append((os.path.join(root, f), rel_path))

for target_zip in zip_targets:
    if target_zip.exists():
        target_zip.unlink()
    with zipfile.ZipFile(target_zip, 'w', zipfile.ZIP_DEFLATED) as z:
        for full_path, arc_name in files_to_pack:
            z.write(full_path, arc_name)
    print(f"Created {target_zip.name} ({target_zip.stat().st_size} bytes, {len(files_to_pack)} files)")

print("Package build complete.")
