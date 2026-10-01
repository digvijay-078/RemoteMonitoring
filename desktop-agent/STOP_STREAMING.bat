@echo off
title Stop RemoteMonitor Streaming Agent
color 0e
echo ========================================================
echo   Stopping RemoteMonitor Desktop Streaming Agent...
echo ========================================================
taskkill /F /IM python.exe /FI "WINDOWTITLE eq RemoteMonitor*" >nul 2>&1
wmic process where "commandline like '%%agent.py start%%'" call terminate >nul 2>&1
echo.
echo [OK] Streaming agent stopped successfully.
echo.
pause
