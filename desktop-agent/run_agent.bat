@echo off
setlocal
cd /d "%~dp0"

set PYTHON_BIN=C:\Users\HP\AppData\Local\Programs\Python\Python311\python.exe

if not exist "%PYTHON_BIN%" (
    echo Python 3.11 not found at %PYTHON_BIN%
    pause
    exit /b 1
)

"%PYTHON_BIN%" agent.py %*
