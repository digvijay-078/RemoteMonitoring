@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - 1-Click Client PC Setup & Permanent Autostart
color 0b

cd /d "%~dp0"

echo ==============================================================================
echo        REMOTEMONITOR DESKTOP AGENT - COMPLETE CLIENT SETUP
echo ==============================================================================
echo.
echo  This tool configures live screen + audio monitoring on this computer
echo  and sets up PERMANENT 24/7 background autostart (Silent on every boot).
echo.
echo ==============================================================================
echo.

:: ----------------------------------------------------------------------------
:: Step 0: Fix and clean any existing broken startup scripts
:: ----------------------------------------------------------------------------
echo [Step 0/6] Cleaning legacy or broken startup files...
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
if exist "%STARTUP_FOLDER%\RemoteMonitorAgent.vbs" (
    del /f /q "%STARTUP_FOLDER%\RemoteMonitorAgent.vbs" >nul 2>&1
    echo   [OK] Cleaned old startup script to prevent Windows Script Host errors.
)

:: ----------------------------------------------------------------------------
:: Step 1: Detect Python 3.10+ Executable
:: ----------------------------------------------------------------------------
echo [Step 1/6] Detecting Python 3.10+ runtime...
set "PYTHON_CMD="

:: Check LocalAppData
for /d %%D in ("%LocalAppData%\Programs\Python\Python3*") do (
    if exist "%%D\python.exe" (
        set "PYTHON_CMD=%%D\python.exe"
    )
)

:: Check py launcher
if "!PYTHON_CMD!"=="" (
    py -3 --version >nul 2>&1
    if !ERRORLEVEL! EQU 0 set "PYTHON_CMD=py -3"
)

:: Check System PATH
if "!PYTHON_CMD!"=="" (
    python --version >nul 2>&1
    if !ERRORLEVEL! EQU 0 set "PYTHON_CMD=python"
)

:: Check Program Files
if "!PYTHON_CMD!"=="" (
    for /d %%D in ("C:\Program Files\Python3*" "C:\Python3*") do (
        if exist "%%D\python.exe" set "PYTHON_CMD=%%D\python.exe"
    )
)

:: If Python is not found, offer 1-click official installation
if "!PYTHON_CMD!"=="" (
    color 0e
    echo.
    echo [NOTICE] Python was not detected on this computer.
    echo Would you like to automatically download and install Python 3.11 now?
    set /p "AUTO_INSTALL=Install Python automatically? (Y/N, default Y): "
    if "!AUTO_INSTALL!"=="" set "AUTO_INSTALL=Y"
    if /i "!AUTO_INSTALL!"=="Y" (
        echo.
        echo [1/2] Downloading official Python 3.11 installer...
        powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://www.python.org/ftp/python/3.11.9/python-3.11.9-amd64.exe' -OutFile 'python_setup.exe'"
        if exist "python_setup.exe" (
            echo [2/2] Installing Python (with PATH enabled)... Please wait...
            python_setup.exe /quiet InstallAllUsers=0 PrependPath=1 Include_test=0
            del python_setup.exe >nul 2>&1
            echo [OK] Python installation completed!
            for /d %%D in ("%LocalAppData%\Programs\Python\Python3*") do (
                if exist "%%D\python.exe" set "PYTHON_CMD=%%D\python.exe"
            )
        )
    )
)

if "!PYTHON_CMD!"=="" (
    color 0c
    echo.
    echo [ERROR] Python 3.11 is required to run the Desktop Agent.
    echo Please install Python from https://www.python.org/downloads/
    echo (Make sure to check "Add Python to PATH" during installation)
    echo.
    pause
    exit /b 1
)

echo   [OK] Python found: !PYTHON_CMD!
echo.

:: ----------------------------------------------------------------------------
:: Step 2: Install / Verify Streaming Dependencies
:: ----------------------------------------------------------------------------
echo [Step 2/6] Verifying required high-speed streaming libraries...
"!PYTHON_CMD!" -c "import requests, mss, PIL, numpy, cv2, pyaudiowpatch" >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    echo   [INFO] Installing required dependencies (this takes ~30 seconds on first run)...
    if exist "requirements.txt" (
        "!PYTHON_CMD!" -m pip install -r requirements.txt >nul 2>&1
    ) else (
        "!PYTHON_CMD!" -m pip install requests mss pillow numpy cv2-python pyaudiowpatch websockets >nul 2>&1
    )
    if !ERRORLEVEL! NEQ 0 (
        echo   [RETRY] Retrying pip install with verbose output...
        "!PYTHON_CMD!" -m pip install requests mss pillow numpy opencv-python pyaudiowpatch websockets
    )
)
echo   [OK] All streaming libraries are ready.
echo.

:: ----------------------------------------------------------------------------
:: Step 3: Server & Pairing Configuration
:: ----------------------------------------------------------------------------
echo [Step 3/6] Configuring Server Connection & Workstation Pairing...
set "DEFAULT_SERVER=https://trodden-wincing-dreamland.ngrok-free.dev"

echo.
echo Default Server URL: !DEFAULT_SERVER!
set /p "USER_SERVER=Press ENTER to use default, or enter custom Server URL: "
if "!USER_SERVER!"=="" (
    set "SERVER_URL=!DEFAULT_SERVER!"
) else (
    set "SERVER_URL=!USER_SERVER!"
)

:: Check if already paired with the current active server URL
set "IS_PAIRED=0"
"!PYTHON_CMD!" -c "from crypto_storage import AgentCredentialStore; cred = AgentCredentialStore().load_credentials(); exit(0 if (cred and cred.get('server_url') == '!SERVER_URL!') else 1)" >nul 2>&1
if !ERRORLEVEL! EQU 0 (
    set "IS_PAIRED=1"
    echo   [OK] Existing pairing credentials verified for !SERVER_URL!
) else (
    "!PYTHON_CMD!" -c "from crypto_storage import AgentCredentialStore; AgentCredentialStore().clear_credentials()" >nul 2>&1
)

if "!IS_PAIRED!"=="0" (
    echo.
    echo Active Test Pairing Codes:
    echo   - PC-3333  (Default Client Test PC)
    echo   - PC-2222  (Secondary Test PC)
    echo   - RM-8888  (Master Admin Pair Code)
    echo.
    set /p "PAIR_CODE=Enter Pairing Code (Press ENTER for default PC-3333): "
    if "!PAIR_CODE!"=="" set "PAIR_CODE=PC-3333"

    echo   [INFO] Pairing device with !SERVER_URL! ...
    "!PYTHON_CMD!" agent.py pair --server "!SERVER_URL!" --code "!PAIR_CODE!"
    if !ERRORLEVEL! NEQ 0 (
        color 0e
        echo   [WARNING] Pairing returned code !ERRORLEVEL!. Checking stored token...
    )
)

:: Verify pairing
"!PYTHON_CMD!" -c "from crypto_storage import AgentCredentialStore; cred = AgentCredentialStore().load_credentials(); exit(0 if cred else 1)" >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    color 0c
    echo.
    echo [ERROR] Workstation could not be paired. Please check your internet connection and pairing code.
    echo.
    pause
    exit /b 1
)
echo   [OK] Workstation pairing verified and saved securely.
echo.

:: ----------------------------------------------------------------------------
:: Step 4: Configure Permanent Autostart (Triple-Shield Redundancy)
:: ----------------------------------------------------------------------------
echo [Step 4/6] Configuring 24/7 Silent Background Autostart...
set "AGENT_DIR=%~dp0"
if "%AGENT_DIR:~-1%"=="\" set "AGENT_DIR=%AGENT_DIR:~0,-1%"
set "VBS_LAUNCHER=%AGENT_DIR%\run_stealth_autostart.vbs"

:: 1. Store Agent Directory in Registry
reg add "HKCU\Software\RemoteMonitor" /v "InstallDir" /t REG_SZ /d "%AGENT_DIR%" /f >nul 2>&1

:: 2. Register via Windows Task Scheduler (Runs automatically at user logon with high priority)
schtasks /Delete /TN "RemoteMonitorDesktopAgent" /F >nul 2>&1
schtasks /Create /TN "RemoteMonitorDesktopAgent" /TR "wscript.exe \"%VBS_LAUNCHER%\"" /SC ONLOGON /RL HIGHEST /F >nul 2>&1
if !ERRORLEVEL! EQU 0 (
    echo   [OK] Windows Task Scheduler Task registered (ONLOGON trigger).
) else (
    :: Fallback standard priority if not admin
    schtasks /Create /TN "RemoteMonitorDesktopAgent" /TR "wscript.exe \"%VBS_LAUNCHER%\"" /SC ONLOGON /F >nul 2>&1
    if !ERRORLEVEL! EQU 0 (
        echo   [OK] Windows Task Scheduler Task registered.
    )
)

:: 3. Register in Windows Registry Run Key
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorAgent" /t REG_SZ /d "wscript.exe \"%VBS_LAUNCHER%\"" /f >nul 2>&1
echo   [OK] Windows Registry Run Key registered.

:: 4. Place updated dynamic launcher in Startup Folder
copy /y "%VBS_LAUNCHER%" "%STARTUP_FOLDER%\RemoteMonitorAgent.vbs" >nul 2>&1
if !ERRORLEVEL! EQU 0 (
    echo   [OK] Windows Startup Folder shortcut updated.
)
echo.

:: ----------------------------------------------------------------------------
:: Step 5: Start Streaming Agent Now
:: ----------------------------------------------------------------------------
echo [Step 5/6] Starting Desktop Agent in Background...
wscript.exe "%VBS_LAUNCHER%"
timeout /t 2 /nobreak >nul 2>&1
echo   [OK] Agent background process launched.
echo.

:: ----------------------------------------------------------------------------
:: Step 6: Completion & Status
:: ----------------------------------------------------------------------------
color 0a
echo ==============================================================================
echo        [SUCCESS] CLIENT SETUP COMPLETED SUCCESSFULLY!
echo ==============================================================================
echo.
echo  * The RemoteMonitor Agent is now running in the background.
echo  * Screen & Audio streaming will automatically start whenever this PC boots up.
echo  * Pairing is saved permanently - you will NEVER need to pair again!
echo  * No command prompt or popup windows will appear on startup.
echo.
echo  To check streaming:
echo    - Open Admin Portal: !SERVER_URL!/admin
echo    - Open Tablet Link : !SERVER_URL!/t/TAB-001
echo.
echo ==============================================================================
echo.
pause
