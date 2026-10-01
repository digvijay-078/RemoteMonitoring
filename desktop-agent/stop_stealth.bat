@echo off
title RemoteMonitor Stopper
cd /d "%~dp0"
echo Stopping RemoteMonitor Desktop Agent...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*agent.py*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" >nul 2>&1
echo [OK] RemoteMonitor Agent stopped.
timeout /t 2 >nul
exit
