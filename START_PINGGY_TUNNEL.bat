@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - Fast Testing Tunnel (Pinggy)
color 0b
cd /d "%~dp0"

echo =====================================================================
echo       REMOTEMONITOR - FAST TESTING TUNNEL (PINGGY)
echo =====================================================================
echo  Target Local Port : 8088 (Unified Ingress Proxy)
echo  Features          : No 1GB Cap, High Bandwidth, WebSockets 30 FPS
echo =====================================================================
echo.
echo Connecting to Pinggy public tunnel...
echo (Once connected, copy the "https://xxxx.pinggy.link" URL shown below)
echo.

ssh -p 443 -R0:localhost:8088 -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=5 a.pinggy.io

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo =====================================================================
    echo [FALLBACK] SSH Connection failed. Trying backup web-tunnel...
    echo =====================================================================
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Invoke-Expression (New-Object Net.WebClient).DownloadString('https://raw.githubusercontent.com/pinggy-io/pinggy-cli/main/install.ps1')"
)
pause
