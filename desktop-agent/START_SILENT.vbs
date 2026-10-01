' ==============================================================================
' RemoteMonitor Desktop Agent - 1-Click Silent Background Launcher
' ==============================================================================
Option Explicit
On Error Resume Next

Dim WshShell, FSO, scriptDir, localAppData, progFiles, progFiles86, sysDrive
Dim candidatePaths, pyPath, finalPython, agentPy, runCmd

Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")

scriptDir = FSO.GetParentFolderName(WScript.ScriptFullName)
agentPy = scriptDir & "\agent.py"

localAppData = WshShell.ExpandEnvironmentStrings("%LocalAppData%")
progFiles = WshShell.ExpandEnvironmentStrings("%ProgramFiles%")
progFiles86 = WshShell.ExpandEnvironmentStrings("%ProgramFiles(x86)%")
sysDrive = WshShell.ExpandEnvironmentStrings("%SystemDrive%")

candidatePaths = Array( _
    localAppData & "\Programs\Python\Python312\pythonw.exe", _
    localAppData & "\Programs\Python\Python312\python.exe", _
    localAppData & "\Programs\Python\Python311\pythonw.exe", _
    localAppData & "\Programs\Python\Python311\python.exe", _
    localAppData & "\Programs\Python\Python310\pythonw.exe", _
    localAppData & "\Programs\Python\Python310\python.exe", _
    progFiles & "\Python312\pythonw.exe", _
    progFiles & "\Python312\python.exe", _
    progFiles & "\Python311\pythonw.exe", _
    progFiles & "\Python311\python.exe", _
    sysDrive & "\Python311\pythonw.exe", _
    sysDrive & "\Python311\python.exe" _
)

finalPython = ""
For Each pyPath In candidatePaths
    If FSO.FileExists(pyPath) Then
        finalPython = pyPath
        Exit For
    End If
Next

If finalPython = "" Then
    finalPython = "pythonw.exe"
End If

If FSO.FileExists(agentPy) Then
    WshShell.CurrentDirectory = scriptDir
    runCmd = """" & finalPython & """ """ & agentPy & """ start"
    WshShell.Run runCmd, 0, False
End If
