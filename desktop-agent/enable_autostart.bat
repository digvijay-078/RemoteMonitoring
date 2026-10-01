@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - Enable Automatic Startup on Boot
color 0a

cd /d "%~dp0"

echo ========================================================
echo   RemoteMonitor Desktop Agent - Auto-Start Setup
echo ========================================================
echo.
echo Configuring 24/7 background autostart on Windows boot...
echo.

set "AGENT_DIR=%~dp0"
if "%AGENT_DIR:~-1%"=="\" set "AGENT_DIR=%AGENT_DIR:~0,-1%"
set "VBS_LAUNCHER=%AGENT_DIR%\run_stealth_autostart.vbs"
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"

:: 1. Store Agent Directory in Registry
reg add "HKCU\Software\RemoteMonitor" /v "InstallDir" /t REG_SZ /d "%AGENT_DIR%" /f >nul 2>&1

:: 2. Task Scheduler Task
schtasks /Delete /TN "RemoteMonitorDesktopAgent" /F >nul 2>&1
schtasks /Create /TN "RemoteMonitorDesktopAgent" /TR "wscript.exe \"%VBS_LAUNCHER%\"" /SC ONLOGON /RL HIGHEST /F >nul 2>&1
if !ERRORLEVEL! NEQ 0 (
    schtasks /Create /TN "RemoteMonitorDesktopAgent" /TR "wscript.exe \"%VBS_LAUNCHER%\"" /SC ONLOGON /F >nul 2>&1
)
echo [OK] Windows Task Scheduler autostart registered.

:: 3. Registry Run Key
reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorAgent" /t REG_SZ /d "wscript.exe \"%VBS_LAUNCHER%\"" /f >nul 2>&1
echo [OK] Windows Registry Run Key registered.

:: 4. Startup Folder Shortcut
copy /y "%VBS_LAUNCHER%" "%STARTUP_FOLDER%\RemoteMonitorAgent.vbs" >nul 2>&1
echo [OK] Windows Startup Folder shortcut updated.

echo.
echo ========================================================
echo   [SUCCESS] Automatic Startup is now ENABLED!
echo ========================================================
echo.
echo The agent will start silently in the background on every reboot.
echo.
pause
