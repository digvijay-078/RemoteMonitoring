@echo off
cd /d "%~dp0"
title RemoteMonitor - Setup and Pair PC 2
echo ========================================================
echo   RemoteMonitor Desktop Agent - PC 2 Setup
echo ========================================================
echo.

echo [1/2] Installing required Python libraries...
python -m pip install -r requirements.txt
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Failed to install Python dependencies. Please ensure Python 3.10+ is installed and on PATH.
    pause
    exit /b 1
)

echo.
echo [2/2] Pairing PC 2 with RemoteMonitor Control Server...
echo.

rem Server URL defaults to Ngrok permanent static domain
set SERVER_URL=https://trodden-wincing-dreamland.ngrok-free.dev
set /p USER_SERVER="Enter Server URL [%SERVER_URL%]: "
if not "%USER_SERVER%"=="" set SERVER_URL=%USER_SERVER%

set PAIR_CODE=PC-2222
set /p USER_CODE="Enter Pairing Code [%PAIR_CODE%]: "
if not "%USER_CODE%"=="" set PAIR_CODE=%USER_CODE%

echo.
echo Pairing with %SERVER_URL% using code %PAIR_CODE% ...
python agent.py pair --server %SERVER_URL% --code %PAIR_CODE%

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================================
    echo   PC 2 PAIRED SUCCESSFULLY!
    echo   Now run 'start_pc2.bat' to start streaming!
    echo ========================================================
) else (
    echo.
    echo [ERROR] Pairing failed. Please check your network connection and server URL.
)

pause
