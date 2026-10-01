@echo off
REM ==============================================================================
REM RemoteMonitor Desktop Agent — Windows Task Scheduler Autostart Installer
REM Configures unattended, 24x7 background startup on Windows logon/boot.
REM ==============================================================================

set TASK_NAME=RemoteMonitorDesktopAgent
set SCRIPT_DIR=%~dp0
set VBS_PATH=%SCRIPT_DIR%run_agent_hidden.vbs

echo ==============================================================================
echo   REMOTEMONITOR DESKTOP AGENT — WINDOWS AUTOSTART INSTALLER
echo ==============================================================================
echo.
echo Task Name   : %TASK_NAME%
echo Launcher    : %VBS_PATH%
echo Target Exec : Python 3.11 aiortc Streaming Daemon
echo.

REM Delete existing task if present
schtasks /Delete /TN "%TASK_NAME%" /F >nul 2>&1

REM Create Scheduled Task triggered at user logon with highest available privilege
schtasks /Create /TN "%TASK_NAME%" /TR "wscript.exe \"%VBS_PATH%\"" /SC ONLOGON /RL HIGHEST /F

if %ERRORLEVEL% equ 0 (
    echo.
    echo [SUCCESS] Scheduled Task '%TASK_NAME%' registered successfully!
    echo The Desktop Agent will now start automatically in the background on every boot.
    echo.
    echo You can start the background task immediately using:
    echo   schtasks /Run /TN "%TASK_NAME%"
) else (
    echo.
    echo [ERROR] Failed to register Scheduled Task (Error Code: %ERRORLEVEL%).
    echo Please run this script as Administrator.
)

echo.
pause
