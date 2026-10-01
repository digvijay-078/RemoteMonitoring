@echo off
REM ==============================================================================
REM RemoteMonitor Desktop Agent — Windows Task Scheduler Autostart Uninstaller
REM ==============================================================================

set TASK_NAME=RemoteMonitorDesktopAgent

echo ==============================================================================
echo   REMOTEMONITOR DESKTOP AGENT — UNINSTALL AUTOSTART TASK
echo ==============================================================================
echo.

schtasks /Delete /TN "%TASK_NAME%" /F

if %ERRORLEVEL% equ 0 (
    echo.
    echo [SUCCESS] Scheduled Task '%TASK_NAME%' was removed.
) else (
    echo.
    echo [NOTICE] Task '%TASK_NAME%' was not found or already removed.
)

echo.
pause
