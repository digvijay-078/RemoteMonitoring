' ==============================================================================
' RemoteMonitor Desktop Agent - Robust Stealth AutoStart Launcher
' Works on ANY Windows PC/Laptop without hardcoded user profile paths.
' Zero popup errors, automatic python detection & windowless background execution.
' ==============================================================================
Option Explicit
On Error Resume Next

Dim WshShell, FSO, scriptDir, localAppData, progFiles, progFiles86, sysDrive
Dim candidatePaths, pyPath, finalPython, agentPy, logFile, runCmd

Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")

' 1. Determine the Directory where this script (or agent) is located
scriptDir = FSO.GetParentFolderName(WScript.ScriptFullName)
agentPy = scriptDir & "\agent.py"

' If this script was copied to Startup folder directly, try to locate agent.py from Registry or standard paths
If Not FSO.FileExists(agentPy) Then
    Dim regDir
    regDir = WshShell.RegRead("HKCU\Software\RemoteMonitor\InstallDir")
    If regDir <> "" And FSO.FileExists(regDir & "\agent.py") Then
        scriptDir = regDir
        agentPy = scriptDir & "\agent.py"
    ElseIf FSO.FileExists("C:\RemoteMonitoring\desktop-agent\agent.py") Then
        scriptDir = "C:\RemoteMonitoring\desktop-agent"
        agentPy = scriptDir & "\agent.py"
    End If
End If

' 2. Resolve Environment Variables
localAppData = WshShell.ExpandEnvironmentStrings("%LocalAppData%")
progFiles = WshShell.ExpandEnvironmentStrings("%ProgramFiles%")
progFiles86 = WshShell.ExpandEnvironmentStrings("%ProgramFiles(x86)%")
sysDrive = WshShell.ExpandEnvironmentStrings("%SystemDrive%")

' 3. List of possible Python binaries (prefer pythonw.exe for true zero-window background execution)
candidatePaths = Array( _
    localAppData & "\Programs\Python\Python312\pythonw.exe", _
    localAppData & "\Programs\Python\Python312\python.exe", _
    localAppData & "\Programs\Python\Python311\pythonw.exe", _
    localAppData & "\Programs\Python\Python311\python.exe", _
    localAppData & "\Programs\Python\Python310\pythonw.exe", _
    localAppData & "\Programs\Python\Python310\python.exe", _
    localAppData & "\Programs\Python\Python39\pythonw.exe", _
    localAppData & "\Programs\Python\Python39\python.exe", _
    progFiles & "\Python312\pythonw.exe", _
    progFiles & "\Python312\python.exe", _
    progFiles & "\Python311\pythonw.exe", _
    progFiles & "\Python311\python.exe", _
    progFiles & "\Python310\pythonw.exe", _
    progFiles & "\Python310\python.exe", _
    progFiles86 & "\Python311\pythonw.exe", _
    progFiles86 & "\Python311\python.exe", _
    sysDrive & "\Python311\pythonw.exe", _
    sysDrive & "\Python311\python.exe", _
    sysDrive & "\Python312\pythonw.exe", _
    sysDrive & "\Python312\python.exe" _
)

finalPython = ""
For Each pyPath In candidatePaths
    If FSO.FileExists(pyPath) Then
        finalPython = pyPath
        Exit For
    End If
Next

' Fallback to PATH if specific path not found
If finalPython = "" Then
    finalPython = "pythonw.exe"
End If

' 4. Ensure Agent directory exists before launching
If FSO.FileExists(agentPy) Then
    WshShell.CurrentDirectory = scriptDir
    runCmd = """" & finalPython & """ """ & agentPy & """ start"
    ' Run completely silent (0 = hide window, False = don't block)
    WshShell.Run runCmd, 0, False
Else
    ' Log error silently to temp directory instead of throwing error popup
    logFile = WshShell.ExpandEnvironmentStrings("%TEMP%") & "\RemoteMonitor_Startup_Error.log"
    Dim f
    Set f = FSO.OpenTextFile(logFile, 8, True)
    f.WriteLine Now & " - Could not find agent.py in: " & scriptDir
    f.Close
End If
