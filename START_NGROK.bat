@echo off
title RemoteMonitor - Permanent Ngrok Tunnel
cd /d "%~dp0"
.\tools\ngrok.exe http --domain=scrambler-unmixable-curve.ngrok-free.dev 8088
