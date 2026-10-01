@echo off
setlocal EnableDelayedExpansion

set "PYTHON_CMD="
if exist "%LocalAppData%\Programs\Python\Python311\python.exe" set "PYTHON_CMD=%LocalAppData%\Programs\Python\Python311\python.exe"
if "!PYTHON_CMD!"=="" (
    for /d %%D in ("%LocalAppData%\Programs\Python\Python3*") do if exist "%%D\python.exe" set "PYTHON_CMD=%%D\python.exe"
)

echo Using Python: "!PYTHON_CMD!"
"!PYTHON_CMD!" agent.py pair --server https://trodden-wincing-dreamland.ngrok-free.dev --code auto
"!PYTHON_CMD!" agent.py status
