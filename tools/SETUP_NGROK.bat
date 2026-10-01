@echo off
setlocal EnableDelayedExpansion
title RemoteMonitor - Ngrok Static Domain Setup
color 0b
cd /d "%~dp0"

echo =====================================================================
echo          REMOTEMONITOR - NGROK PERMANENT DOMAIN CONFIGURATION
echo =====================================================================
echo.
echo  Ngrok provides 1 FREE PERMANENT STATIC DOMAIN that never expires!
echo.
echo  1. Open https://dashboard.ngrok.com/signup and login (Free)
echo  2. Copy your AuthToken from: https://dashboard.ngrok.com/get-started/your-authtoken
echo  3. Claim your Free Static Domain from: https://dashboard.ngrok.com/domains
echo.
echo =====================================================================
echo.

set /p "USER_TOKEN=Enter your Ngrok AuthToken: "
if "!USER_TOKEN!"=="" (
    color 0c
    echo [ERROR] AuthToken is required!
    pause
    exit /b 1
)

echo.
echo [1/2] Saving Ngrok Authtoken...
.\ngrok.exe config add-authtoken !USER_TOKEN!
if !ERRORLEVEL! NEQ 0 (
    color 0c
    echo [ERROR] Failed to save authtoken.
    pause
    exit /b 1
)
echo   [OK] Authtoken configured!

echo.
set /p "USER_DOMAIN=Enter your Ngrok Static Domain (e.g. my-app.ngrok-free.app): "
if "!USER_DOMAIN!"=="" (
    echo   [INFO] No domain specified. A random Ngrok URL will be used.
    set "USER_DOMAIN="
)

echo.
echo [2/2] Generating START_NGROK.bat launcher...
if "!USER_DOMAIN!"=="" (
    (
        echo @echo off
        echo title RemoteMonitor - Permanent Ngrok Tunnel
        echo cd /d "%%~dp0"
        echo .\tools\ngrok.exe http 8088
    ) > "..\START_NGROK.bat"
) else (
    (
        echo @echo off
        echo title RemoteMonitor - Permanent Ngrok Tunnel
        echo cd /d "%%~dp0"
        echo .\tools\ngrok.exe http --domain=!USER_DOMAIN! 8088
    ) > "..\START_NGROK.bat"
)

echo   [OK] START_NGROK.bat created successfully!
echo.
echo =====================================================================
echo   SETUP COMPLETE!
echo   Run START_NGROK.bat anytime to launch your permanent tunnel!
echo =====================================================================
echo.
pause
