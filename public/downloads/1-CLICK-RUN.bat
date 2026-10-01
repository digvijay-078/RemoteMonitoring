@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor Desktop Agent - 1-Click Client Setup
color 0b

echo =====================================================================
echo       REMOTEMONITOR DESKTOP AGENT - 1-CLICK CLIENT SETUP
echo =====================================================================

:: =====================================================================
:: CONFIGURATION: Server URL and Cloud Package Link
:: =====================================================================
:: Paste your active Pinggy, Railway, or Ngrok Tunnel URL here:
set "SERVER_URL=https://trodden-wincing-dreamland.ngrok-free.dev"

:: High-Speed Cloud Direct Download Link (GitHub Releases, Google Drive, or Server fallback)
set "EXE_URL=%SERVER_URL%/downloads/RemoteMonitor-Setup.exe"

set "AGENT_DIR=%USERPROFILE%\RemoteMonitorAgent"
set "AGENT_EXE=%AGENT_DIR%\RemoteMonitor-Setup.exe"

echo  Server Target : %SERVER_URL%
echo  Date / Time   : %DATE% %TIME%
echo =====================================================================
echo.

:: 1. If running inside existing directory with standalone EXE
if exist "%~dp0RemoteMonitor-Setup.exe" (
    echo [OK] Found local standalone executable in current folder.
    start "" "%~dp0RemoteMonitor-Setup.exe" --server "%SERVER_URL%"
    goto :LaunchSuccess
)

if exist "%~dp0dist\RemoteMonitor-Setup.exe" (
    echo [OK] Found local standalone executable in dist folder.
    start "" "%~dp0dist\RemoteMonitor-Setup.exe" --server "%SERVER_URL%"
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

    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
        "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13; " ^
        "$url = '%EXE_URL%'.Trim(); " ^
        "$out = '%AGENT_EXE%'; " ^
        "try { " ^
        "    $wc = New-Object System.Net.WebClient; " ^
        "    $wc.Headers.Add('ngrok-skip-browser-warning', '1'); " ^
        "    $wc.Headers.Add('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) RemoteMonitorSetup/2.0'); " ^
        "    Write-Host 'Connecting to download stream...' -ForegroundColor Cyan; " ^
        "    $wc.DownloadFile($url, $out); " ^
        "    if ((Get-Item $out).Length -gt 50000000) { " ^
        "        Write-Host '[OK] Standalone Agent downloaded successfully.' -ForegroundColor Green " ^
        "    } else { throw 'Downloaded file size is too small' } " ^
        "} catch { " ^
        "    try { " ^
        "        Write-Host 'Retrying with bits/web request...' -ForegroundColor Yellow; " ^
        "        Invoke-WebRequest -Uri $url -Headers @{ 'ngrok-skip-browser-warning' = '1'; 'User-Agent' = 'RemoteMonitorSetup/2.0' } -OutFile $out -UseBasicParsing; " ^
        "        if ((Get-Item $out).Length -gt 50000000) { " ^
        "            Write-Host '[OK] Standalone Agent downloaded successfully.' -ForegroundColor Green " ^
        "        } else { throw 'File size check failed' } " ^
        "    } catch { " ^
        "        Write-Host '[ERROR] Download failed: ' $_ -ForegroundColor Red; " ^
        "        exit 1 " ^
        "    } " ^
        "}"
)

if not exist "%AGENT_EXE%" (
    color 0c
    echo.
    echo =====================================================================
    echo [ERROR] Failed to download RemoteMonitor Agent.
    echo =====================================================================
    echo Please check your internet connection or download directly:
    echo %EXE_URL%
    echo.
    pause
    exit /b 1
)

:: 4. Unblock file from Windows SmartScreen (removes Mark-of-the-Web)
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Unblock-File -Path '%AGENT_EXE%' -ErrorAction SilentlyContinue } catch {}"

:: 5. Create Desktop Shortcut for 1-click access
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ws = New-Object -ComObject WScript.Shell; $s = $ws.CreateShortcut([System.IO.Path]::Combine([Environment]::GetFolderPath('Desktop'), 'RemoteMonitor Agent.lnk')); $s.TargetPath = '%AGENT_EXE%'; $s.Arguments = '--server \"%SERVER_URL%\"'; $s.WorkingDirectory = '%AGENT_DIR%'; $s.Save()" >nul 2>&1

:: 6. Launch Standalone GUI
echo.
echo [3/3] Launching RemoteMonitor Desktop Agent Control Panel...
start "" "%AGENT_EXE%" --server "%SERVER_URL%"

:LaunchSuccess
echo.
echo =====================================================================
echo   [SUCCESS] REMOTEMONITOR AGENT IS NOW RUNNING!
echo.
echo   * Connected to: %SERVER_URL%
echo   * Control Panel GUI is opening on screen.
echo   * Desktop shortcut created: "RemoteMonitor Agent"
echo =====================================================================
echo.
echo This window will auto-close in 8 seconds...
timeout /t 8 >nul
exit /b 0
