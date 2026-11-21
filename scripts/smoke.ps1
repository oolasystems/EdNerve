<#
.SYNOPSIS
  Production smoke check for OolaEdNerve API (30 seconds, exit 0 = healthy).

.EXAMPLE
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts/smoke.ps1

.NOTES
  Defaults target the seeded demo institution admin on production.
  Read-only: no writes.
#>

[CmdletBinding()]
param(
    [string]$ApiBaseUrl = "https://ednerve-api.oolasystems.com/api",
    [string]$Email      = $env:SMOKE_EMAIL,
    [string]$Password   = $env:SMOKE_PASSWORD
)

if ([string]::IsNullOrWhiteSpace($Email) -or [string]::IsNullOrWhiteSpace($Password)) {
    Write-Host "SMOKE_EMAIL and SMOKE_PASSWORD (or -Email/-Password) are required. Do not use demo defaults." -ForegroundColor Red
    exit 2
}

$ErrorActionPreference = "Stop"
$failures = @()
$checks   = 0

function Fail([string]$label) { $script:failures += $label }

function Step { $script:checks++ }

function OkLine([string]$label) {
    Write-Host ("  [OK]   " + $label) -ForegroundColor Green
}

function FailLine([string]$label) {
    Write-Host ("  [FAIL] " + $label) -ForegroundColor Red
    $script:failures += $label
}

Write-Host ""
Write-Host "OolaEdNerve - production smoke check" -ForegroundColor Cyan
Write-Host ("API: " + $ApiBaseUrl)
Write-Host ""

# 1. Health
Step
try {
    $health = Invoke-RestMethod -Uri ("$ApiBaseUrl/health") -Method Get -TimeoutSec 15
    if ($health.status -eq "ok") { OkLine "/api/health returns ok" } else { FailLine "/api/health status=" + $health.status }
} catch {
    FailLine ("/api/health - " + $_.Exception.Message)
}

# 2. Login
Step
$token = $null
try {
    $body = @{ email = $Email; password = $Password } | ConvertTo-Json
    $loginResp = Invoke-RestMethod -Uri ("$ApiBaseUrl/auth/login") -Method Post -ContentType "application/json" -Body $body -TimeoutSec 15
    $token = $loginResp.token
    if ($token) { OkLine "login as $Email" } else { FailLine "login - no token returned" }
} catch {
    FailLine ("login - " + $_.Exception.Message)
}

if ($token) {
    $headers = @{ Authorization = "Bearer $token" }

    foreach ($p in @("/institution", "/faculties", "/schools", "/academic-units", "/admissions/intakes")) {
        Step
        try {
            $r = Invoke-WebRequest -Uri ("$ApiBaseUrl$p") -Headers $headers -UseBasicParsing -TimeoutSec 15
            if ($r.StatusCode -eq 200) { OkLine ("$p loads") } else { FailLine ("$p status=" + $r.StatusCode) }
        } catch {
            FailLine ("$p - " + $_.Exception.Message)
        }
    }
}

# 6. Public portal (no auth)
Step
try {
    $pub = Invoke-WebRequest -Uri ("$ApiBaseUrl/public/admissions/amu") -UseBasicParsing -TimeoutSec 15
    if ($pub.StatusCode -eq 200) { OkLine "public /api/public/admissions/amu loads" } else { FailLine ("public portal status=" + $pub.StatusCode) }
} catch {
    FailLine ("public portal - " + $_.Exception.Message)
}

Write-Host ""
Write-Host ("Checks: $checks  |  Failures: " + $failures.Count) -ForegroundColor $(if ($failures.Count -gt 0) { "Red" } else { "Green" })
Write-Host ""

if ($failures.Count -gt 0) {
    Write-Host "SMOKE FAIL - do not declare this deploy good until every check passes." -ForegroundColor Red
    exit 1
}
Write-Host "SMOKE PASS - ready for sign-off." -ForegroundColor Green
exit 0
