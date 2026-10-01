@echo off
title RemoteMonitor - Permanent Ngrok Tunnel (8088)
color 0a
cd /d "%~dp0"

echo =====================================================================
echo       REMOTEMONITOR - PERMANENT NGROK TUNNEL
echo =====================================================================
echo  Domain: https://trodden-wincing-dreamland.ngrok-free.dev
echo  Local Port: 8088 (Unified Ingress Proxy)
echo =====================================================================
echo.

.\tools\ngrok.exe http --domain=trodden-wincing-dreamland.ngrok-free.dev 8088
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [RETRY] Launching with --url flag...
    .\tools\ngrok.exe http --url=https://trodden-wincing-dreamland.ngrok-free.dev 8088
)
pause
