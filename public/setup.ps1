# RemoteMonitor 1-Click Client Setup Script (Bypasses Windows 11 Smart App Control & Mark of the Web)
$ErrorActionPreference = "Stop"

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "       REMOTEMONITOR DESKTOP AGENT - 1-CLICK SETUP           " -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""

$serverUrl = "https://scrambler-unmixable-curve.ngrok-free.dev"
$exeUrl = "$serverUrl/downloads/RemoteMonitor-Setup.exe"
$agentDir = [System.IO.Path]::Combine($env:USERPROFILE, "RemoteMonitorAgent")
if (-not (Test-Path $agentDir)) {
    New-Item -ItemType Directory -Path $agentDir -Force | Out-Null
}
$destExe = [System.IO.Path]::Combine($agentDir, "RemoteMonitor-Setup.exe")

Write-Host "[1/3] Downloading latest Desktop Agent Standalone Setup..." -ForegroundColor Yellow
Write-Host "      (Includes Built-in GUI, 30 FPS Stream Engine & Audio Intercom)" -ForegroundColor DarkGray
try {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls13
    $headers = @{
        'ngrok-skip-browser-warning' = '1'
        'User-Agent' = 'RemoteMonitorSetup/2.0'
    }
    
    # Try WebClient first for fastest streaming download
    try {
        $wc = New-Object System.Net.WebClient
        $wc.Headers.Add('ngrok-skip-browser-warning', '1')
        $wc.Headers.Add('User-Agent', 'RemoteMonitorSetup/2.0')
        $wc.DownloadFile($exeUrl, $destExe)
    } catch {
        Invoke-WebRequest -Uri $exeUrl -Headers $headers -OutFile $destExe -UseBasicParsing
    }

    $fileSize = (Get-Item $destExe).Length
    if ($fileSize -lt 10000000) {
        throw "Downloaded package is incomplete ($fileSize bytes). Expected ~118 MB."
    }

    Write-Host "[OK] Download completed successfully ($([Math]::Round($fileSize / 1MB, 1)) MB)." -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Failed to download setup: $_" -ForegroundColor Red
    Write-Host "Please download directly from: $exeUrl" -ForegroundColor Yellow
    exit 1
}

Write-Host "[2/3] Removing Internet Mark-of-the-Web and creating Desktop shortcut..." -ForegroundColor Yellow
try {
    Unblock-File -Path $destExe -ErrorAction SilentlyContinue
    $ws = New-Object -ComObject WScript.Shell
    $desktopPath = [Environment]::GetFolderPath('Desktop')
    $shortcut = $ws.CreateShortcut([System.IO.Path]::Combine($desktopPath, 'RemoteMonitor Agent.lnk'))
    $shortcut.TargetPath = $destExe
    $shortcut.WorkingDirectory = $agentDir
    $shortcut.Save()
    Write-Host "[OK] File unblocked & Desktop shortcut created." -ForegroundColor Green
} catch {
    Write-Host "[INFO] Continuing..." -ForegroundColor DarkGray
}

Write-Host "[3/3] Launching RemoteMonitor Desktop Agent Control Panel..." -ForegroundColor Yellow
Start-Process -FilePath $destExe

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "[SUCCESS] RemoteMonitor Desktop Agent is now running!" -ForegroundColor Green
Write-Host "Check your screen for the Dark Blue Control Panel GUI." -ForegroundColor White
Write-Host "Click 'Start Live Broadcast' to stream to Admin Portal." -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
