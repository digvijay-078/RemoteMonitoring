@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - Disable Automatic Startup on Boot
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
) else (
    echo [INFO] Not found in Windows Startup Folder.
)

:: 2. Remove from Registry Run Key
reg delete "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v "RemoteMonitorAgent" /f >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [OK] Removed from Windows Registry Run Key.
) else (
    echo [INFO] Not found in Windows Registry.
)

echo.
echo ========================================================
echo   [SUCCESS] Automatic Startup has been DISABLED.
echo ========================================================
echo.
pause
