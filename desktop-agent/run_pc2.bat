@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor Desktop Agent - PC 2
color 0b

cd /d "%~dp0"

echo ========================================================
echo   RemoteMonitor Desktop Agent - PC 2 Automatic Setup
echo ========================================================
echo.

:: 1. Check if files were extracted or running inside zip
if not exist "agent.py" (
    color 0c
    echo [ERROR] agent.py not found in current folder!
    echo.
    echo [NOTE] Please make sure you EXTRACTED the ZIP file first.
    echo        Right-click RemoteMonitor-Agent.zip and select "Extract All".
    echo        Do not run directly from inside the ZIP file.
    echo.
    pause
    exit /b 1
)

:: 2. Detect real Python executable (must have pip)
set "PYTHON_CMD="

:: Priority A: Python in LocalAppData (Standard Windows Python 3.10+)
for /d %%D in ("%LocalAppData%\Programs\Python\Python3*") do (
    if exist "%%D\python.exe" (
        "%%D\python.exe" -m pip --version >nul 2>&1
        if !ERRORLEVEL! EQU 0 (
            set "PYTHON_CMD=%%D\python.exe"
        )
    )
)

:: Priority B: py launcher
if "!PYTHON_CMD!"=="" (
    py -3 -m pip --version >nul 2>&1
    if !ERRORLEVEL! EQU 0 (
        set "PYTHON_CMD=py -3"
    )
)

:: Priority C: system PATH python
if "!PYTHON_CMD!"=="" (
    python -m pip --version >nul 2>&1
    if !ERRORLEVEL! EQU 0 (
        set "PYTHON_CMD=python"
    )
)

:: Priority D: C:\Program Files\Python* or C:\Python*
if "!PYTHON_CMD!"=="" (
    for /d %%D in ("C:\Program Files\Python3*" "C:\Python3*") do (
        if exist "%%D\python.exe" (
            "%%D\python.exe" -m pip --version >nul 2>&1
            if !ERRORLEVEL! EQU 0 (
                set "PYTHON_CMD=%%D\python.exe"
            )
        )
    )
)

if "!PYTHON_CMD!"=="" (
    color 0c
    echo [ERROR] Python was not found on this computer!
    echo.
    echo Please install Python 3.11:
    echo 1. Download: https://www.python.org/downloads/
    echo 2. When installing, CHECK THE BOX:
    echo    "Add python.exe to PATH"
    echo.
    pause
    exit /b 1
)

echo [OK] Using Python: "!PYTHON_CMD!"
echo.

:: 3. Check and install dependencies
echo [1/3] Checking required libraries (mss, opencv, pyaudiowpatch)...
"!PYTHON_CMD!" -c "import requests, mss, PIL, numpy, cv2, pyaudiowpatch" >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    echo [INFO] Installing required high-speed streaming libraries. Please wait...
    echo.
    if exist "requirements.txt" (
        "!PYTHON_CMD!" -m pip install -r requirements.txt
    ) else (
        "!PYTHON_CMD!" -m pip install requests mss pillow numpy websockets sounddevice pywin32 aiortc bettercam av opencv-python pyaudiowpatch
    )
    if !ERRORLEVEL! NEQ 0 (
        color 0c
        echo.
        echo [ERROR] Failed to install Python dependencies via pip.
        echo Please check your internet connection.
        echo.
        pause
        exit /b 1
    )
)
echo [OK] All streaming libraries verified.
echo.

:: 4. Pairing check
echo [2/3] Checking PC 2 pairing status and server URL...
"!PYTHON_CMD!" -c "import sys; from crypto_storage import AgentCredentialStore; cred = AgentCredentialStore().load_credentials(); sys.exit(0 if cred and 'trodden-wincing-dreamland' in cred.get('server_url','') else 1)" >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    echo [INFO] Pairing / Updating PC 2 with active Control Server...
    "!PYTHON_CMD!" agent.py pair --server https://trodden-wincing-dreamland.ngrok-free.dev --code PC-2222
    if !ERRORLEVEL! NEQ 0 (
        color 0c
        echo.
        echo [ERROR] Pairing failed. Please verify the Ngrok server URL and network.
        echo.
        pause
        exit /b 1
    )
) else (
    echo [OK] PC 2 is verified and paired!
)
echo.

:: 4b. Register Automatic Startup on Windows Boot (Option 1)
set "AGENT_DIR=%~dp0"
if "%AGENT_DIR:~-1%"=="\" set "AGENT_DIR=%AGENT_DIR:~0,-1%"
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "LAUNCHER_VBS=%AGENT_DIR%\run_stealth_autostart.vbs"

(
    echo ' RemoteMonitor AutoStart Stealth Runner
    echo Set WshShell = CreateObject("WScript.Shell"^)
    echo WshShell.CurrentDirectory = "%AGENT_DIR%"
    echo WshShell.Run "cmd.exe /c ""!PYTHON_CMD!"" agent.py start", 0, False
) > "%LAUNCHER_VBS%" 2>nul

copy /y "%LAUNCHER_VBS%" "%STARTUP_FOLDER%\RemoteMonitorAgent.vbs" >nul 2>&1
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorDesktopAgent" /t REG_SZ /d "wscript.exe \"%LAUNCHER_VBS%\"" /f >nul 2>&1
echo [OK] Automatic Boot Startup is registered (starts silently on every PC boot).
echo.

:: 5. Launch Streaming Daemon
echo [3/3] Starting Desktop Streaming Daemon...
echo ========================================================
echo   Live streaming active. Keep this window OPEN.
echo   Press Ctrl+C to stop streaming.
echo ========================================================
echo.

"!PYTHON_CMD!" agent.py start --server https://trodden-wincing-dreamland.ngrok-free.dev --ws wss://trodden-wincing-dreamland.ngrok-free.dev

if !ERRORLEVEL! NEQ 0 (
    color 0c
    echo.
    echo [ERROR] Agent stopped with error code !ERRORLEVEL!.
    echo.
    pause
)
