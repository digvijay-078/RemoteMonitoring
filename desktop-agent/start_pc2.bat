@echo off
cd /d "%~dp0"
title RemoteMonitor - Streaming Daemon (PC 2)
echo ========================================================
echo   RemoteMonitor Desktop Agent - PC 2 Streaming Daemon
echo ========================================================
echo.

echo Connecting to RemoteMonitor via secure Ngrok permanent tunnel...
python agent.py start --ws wss://trodden-wincing-dreamland.ngrok-free.dev
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] Agent stopped with error code %ERRORLEVEL%.
    pause
)
