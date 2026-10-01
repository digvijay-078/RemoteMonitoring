@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor Desktop Agent - 1-Click Client Setup
color 0b

echo =====================================================================
echo       REMOTEMONITOR DESKTOP AGENT - 1-CLICK CLIENT SETUP
echo =====================================================================
echo Server Target : https://scrambler-unmixable-curve.ngrok-free.dev
echo Time          : %DATE% %TIME%
echo =====================================================================
echo.

set "SERVER_URL=https://scrambler-unmixable-curve.ngrok-free.dev"
set "EXE_URL=%SERVER_URL%/downloads/RemoteMonitor-Setup.exe"
set "AGENT_DIR=%USERPROFILE%\RemoteMonitorAgent"
set "AGENT_EXE=%AGENT_DIR%\RemoteMonitor-Setup.exe"

:: 1. If running inside existing directory with standalone EXE
if exist "%~dp0RemoteMonitor-Setup.exe" (
    echo [OK] Found local standalone executable in current folder.
    start "" "%~dp0RemoteMonitor-Setup.exe"
    goto :LaunchSuccess
)

if exist "%~dp0dist\RemoteMonitor-Setup.exe" (
    echo [OK] Found local standalone executable in dist folder.
    start "" "%~dp0dist\RemoteMonitor-Setup.exe"
    goto :LaunchSuccess
)

:: 2. Setup Agent directory
echo [1/3] Preparing Agent Directory at "%AGENT_DIR%"...
if not exist "%AGENT_DIR%" mkdir "%AGENT_DIR%"
cd /d "%AGENT_DIR%"

:: 3. Download standalone executable package if not already present or incomplete
set "NEED_DOWNLOAD=1"
if exist "%AGENT_EXE%" (
    for %%F in ("%AGENT_EXE%") do (
        if %%~zF GTR 50000000 (
            echo [OK] Standalone Agent package already cached ^(%%~zF bytes^).
            set "NEED_DOWNLOAD=0"
        )
    )
)

if "!NEED_DOWNLOAD!"=="1" (
    echo [2/3] Downloading latest Standalone RemoteMonitor Desktop Agent...
    echo       ^(Includes built-in GUI, 30 FPS Stream Engine, and Audio Intercom^)
    echo       Downloading ~118 MB package... Please wait a few moments...
    echo.

    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13; try { $wc = New-Object System.Net.WebClient; $wc.Headers.Add('ngrok-skip-browser-warning', '1'); $wc.Headers.Add('User-Agent', 'RemoteMonitorSetup/2.0'); $wc.DownloadFile('%EXE_URL%', '%AGENT_EXE%'); Write-Host '[OK] Standalone Agent downloaded successfully.' -ForegroundColor Green } catch { try { Invoke-WebRequest -Uri '%EXE_URL%' -Headers @{ 'ngrok-skip-browser-warning' = '1'; 'User-Agent' = 'RemoteMonitorSetup/2.0' } -OutFile '%AGENT_EXE%' -UseBasicParsing; Write-Host '[OK] Standalone Agent downloaded successfully.' -ForegroundColor Green } catch { Write-Host '[ERROR] Download failed: ' $_ -ForegroundColor Red; exit 1 } }"
)

if not exist "%AGENT_EXE%" (
    color 0c
    echo.
    echo [ERROR] Failed to download RemoteMonitor Agent.
    echo Please check your internet connection or download directly in your browser:
    echo %EXE_URL%
    echo.
    pause
    exit /b 1
)

:: 4. Unblock file from Windows SmartScreen (removes Mark-of-the-Web)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Unblock-File -Path '%AGENT_EXE%' -ErrorAction SilentlyContinue } catch {}"

:: 5. Create Desktop Shortcut for 1-click access
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut([System.IO.Path]::Combine([Environment]::GetFolderPath('Desktop'), 'RemoteMonitor Agent.lnk')); $s.TargetPath = '%AGENT_EXE%'; $s.WorkingDirectory = '%AGENT_DIR%'; $s.Save()" >nul 2>&1

:: 6. Launch Standalone GUI
echo.
echo [3/3] Launching RemoteMonitor Desktop Agent Control Panel...
start "" "%AGENT_EXE%"

:LaunchSuccess
echo.
echo =====================================================================
echo   [SUCCESS] REMOTEMONITOR AGENT IS NOW RUNNING!
echo.
echo   * The Control Panel GUI is opening on your screen now.
echo   * Click "Start Live Broadcast" to stream 30 FPS to Admin Portal.
echo   * Desktop shortcut created: "RemoteMonitor Agent"
echo =====================================================================
echo.
echo Press any key to close this window, or it will auto-close in 10 seconds.
timeout /t 10 >nul
exit /b 0
