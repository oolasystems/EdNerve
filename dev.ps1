<#
.SYNOPSIS
  Start / stop / status the OolaEdNerve dev apps on this machine.

.EXAMPLE
  .\dev.ps1 start     # opens one PowerShell window per app (API, Web, Mobile)
  .\dev.ps1 stop      # stops whatever is listening on ports 8000, 5173, 8081
  .\dev.ps1 status    # shows which apps are listening right now
#>

param(
  [ValidateSet('start', 'stop', 'status')]
  [string]$Action = 'status'
)

$ErrorActionPreference = 'SilentlyContinue'
$root = $PSScriptRoot

$apps = @(
  @{ Name = 'API';    Dir = Join-Path $root 'ednerve-api';    Port = 8000; Command = "C:\xampp\php\php.exe artisan serve" },
  @{ Name = 'Web';    Dir = Join-Path $root 'ednerve-web';    Port = 5173; Command = 'npm run dev' },
  @{ Name = 'Mobile'; Dir = Join-Path $root 'ednerve-mobile'; Port = 8081; Command = 'npx expo start' }
)

function Get-PortOwner([int]$Port) {
  (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -First 1 -ExpandProperty OwningProcess)
}

switch ($Action) {
  'status' {
    Write-Host "`nOolaEdNerve dev-app status:" -ForegroundColor Cyan
    foreach ($app in $apps) {
      $owner = Get-PortOwner $app.Port
      if ($owner) {
        $proc = Get-Process -Id $owner -ErrorAction SilentlyContinue
        Write-Host ("  [RUNNING] {0,-6} port {1}  (PID {2}, {3})" -f $app.Name, $app.Port, $owner, $proc.ProcessName) -ForegroundColor Green
      } else {
        Write-Host ("  [STOPPED] {0,-6} port {1}" -f $app.Name, $app.Port) -ForegroundColor Yellow
      }
    }
    Write-Host ""
  }

  'start' {
    # Warn if MySQL is down (API needs it)
    & C:\xampp\mysql\bin\mysqladmin.exe -u root ping 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
      Write-Host "WARNING: MySQL does not appear to be running (XAMPP Control Panel -> MySQL -> Start)." -ForegroundColor Yellow
    }

    foreach ($app in $apps) {
      $owner = Get-PortOwner $app.Port
      if ($owner) {
        Write-Host ("[SKIP] {0} already running on port {1} (PID {2})" -f $app.Name, $app.Port, $owner) -ForegroundColor Yellow
        continue
      }
      $command = "Set-Location '$($app.Dir)'; Write-Host '=== $($app.Name) (port $($app.Port)) - close this window with Ctrl+C or .\dev.ps1 stop ===' -ForegroundColor Cyan; $($app.Command)"
      Start-Process powershell.exe -ArgumentList '-NoExit', '-Command', $command -WindowStyle Normal
      Write-Host ("[STARTED] {0} on port {1}" -f $app.Name, $app.Port) -ForegroundColor Green
    }
    Write-Host "`nWeb: http://localhost:5173  |  API: http://127.0.0.1:8000/api/health  |  Expo: scan the QR in the Mobile window\n"
  }

  'stop' {
    foreach ($app in $apps) {
      $owner = Get-PortOwner $app.Port
      if (-not $owner) {
        Write-Host ("[SKIP] {0} not listening on port {1}" -f $app.Name, $app.Port) -ForegroundColor Yellow
        continue
      }
      Stop-Process -Id $owner -Force
      Write-Host ("[STOPPED] {0} (PID {1}, port {2})" -f $app.Name, $owner, $app.Port) -ForegroundColor Green
    }
  }
}
