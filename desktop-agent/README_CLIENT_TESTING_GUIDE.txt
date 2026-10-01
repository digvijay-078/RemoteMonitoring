================================================================================
          REMOTEMONITOR - CLIENT SETUP & TESTING GUIDE
================================================================================

This package allows any Windows laptop/PC to stream screen + audio to the
RemoteMonitoring server automatically and permanently on startup.

--------------------------------------------------------------------------------
STEP 1: 1-CLICK SETUP ON ANY WINDOWS PC / LAPTOP (e.g. Pratiksha's PC)
--------------------------------------------------------------------------------
1. Copy/Extract the `desktop-agent` folder to the laptop.
2. Double-click:
   -->  SETUP_CLIENT_PC.bat

3. The setup script will automatically:
   - Clean any broken startup scripts (permanently eliminating Error 80070002).
   - Detect or install Python automatically.
   - Install required streaming packages.
   - Connect to Server: https://trodden-wincing-dreamland.ngrok-free.dev
   - Pair device (Default Code: PC-3333 or RM-8888).
   - Configure Triple-Shield Permanent Autostart (Windows Task Scheduler + Registry + Startup).
   - Launch the background agent immediately.

From now on, whenever this laptop restarts or boots up, it will connect
silently in the background without opening any command prompt or showing any errors!

--------------------------------------------------------------------------------
STEP 2: VIEW LIVE FEED ON TABLET / PHONE
--------------------------------------------------------------------------------
1. Open Chrome on Tablet or Mobile:
   -->  https://trodden-wincing-dreamland.ngrok-free.dev/t/TAB-001
   (Pairing Code if requested: TAB-7777 or TAB-8888)

2. Features available:
   - Real-time 30 FPS screen stream.
   - PC Audio listen button.
   - 🎙️ Two-way Walkie-Talkie Mic (Speak from tablet into PC speakers).

--------------------------------------------------------------------------------
STEP 3: ADMIN CONTROL ROOM / DASHBOARD
--------------------------------------------------------------------------------
1. Open Admin Panel:
   -->  https://trodden-wincing-dreamland.ngrok-free.dev/admin

2. Login Credentials:
   - Email:    admin@remotemonitor.internal (Password: password)
   - Or:       admin@remotemonitor.local (Password: admin123456)

--------------------------------------------------------------------------------
MANAGEMENT SCRIPTS (IN DESKTOP-AGENT FOLDER):
--------------------------------------------------------------------------------
- SETUP_CLIENT_PC.bat    --> 1-Click Complete Install & Permanent Autostart
- 1-CLICK-RUN.bat        --> Run agent interactively with console output
- START_SILENT.vbs       --> Run agent silently in background immediately
- STOP_STREAMING.bat     --> Stop streaming agent
- UNINSTALL_CLIENT.bat   --> Completely remove autostart and background tasks
================================================================================
