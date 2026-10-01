' RemoteMonitor Desktop Agent Hidden Launcher
' Runs the Python 3.11 streaming daemon without displaying a console window.

Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")

ScriptDir = FSO.GetParentFolderName(WScript.ScriptFullName)
PythonExe = "C:\Users\HP\AppData\Local\Programs\Python\Python311\python.exe"

If Not FSO.FileExists(PythonExe) Then
    PythonExe = "python.exe"
End If

Cmd = """" & PythonExe & """ -u """ & ScriptDir & "\agent.py"" start"

' 0 = Hide window, False = Do not block execution
WshShell.CurrentDirectory = ScriptDir
WshShell.Run Cmd, 0, False
