@echo off
title RemoteMonitor Launcher
cd /d "%~dp0"
start "" wscript.exe "%~dp0start_stealth.vbs"
exit
