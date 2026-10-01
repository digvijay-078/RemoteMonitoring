' RemoteMonitor Stealth Background Launcher
' Starts the RemoteMonitor Desktop Agent completely hidden without any CMD or console window (SW_HIDE = 0)
Set fso = CreateObject("Scripting.FileSystemObject")
scriptDir = fso.GetParentFolderName(WScript.ScriptFullName)

Set WshShell = CreateObject("WScript.Shell")
cmd = "cmd.exe /c cd /d """ & scriptDir & """ && python.exe agent.py start"

' 0 = SW_HIDE (No console window, no taskbar item, silent background execution)
WshShell.Run cmd, 0, False
