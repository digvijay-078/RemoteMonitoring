@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - Disable & Clean Autostart
color 0e

cd /d "%~dp0"

echo ========================================================
echo   RemoteMonitor Desktop Agent - Disable Auto-Start
echo ========================================================
echo.

:: 1. Remove from Windows Startup Folder
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "STARTUP_VBS=%STARTUP_FOLDER%\RemoteMonitorAgent.vbs"

if exist "%STARTUP_VBS%" (
    del /f /q "%STARTUP_VBS%" >nul 2>&1
    echo [OK] Removed from Windows Startup Folder.
)

:: 2. Remove Task Scheduler Task
schtasks /Delete /TN "RemoteMonitorDesktopAgent" /F >nul 2>&1
echo [OK] Removed from Windows Task Scheduler.

:: 3. Remove from Registry Run Key
reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorAgent" /f >nul 2>&1
reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorDesktopAgent" /f >nul 2>&1
reg delete "HKCU\Software\RemoteMonitor" /f >nul 2>&1
echo [OK] Removed from Windows Registry.

:: 4. Stop running agent processes
taskkill /F /IM pythonw.exe /FI "WINDOWTITLE eq RemoteMonitor*" >nul 2>&1
wmic process where "commandline like '%agent.py start%'" call terminate >nul 2>&1

echo.
echo ========================================================
echo   [SUCCESS] Automatic Startup has been Completely Removed.
echo ========================================================
echo.
pause
