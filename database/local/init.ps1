# Initialise a project-local MySQL 8.4 data directory (one-time, dev only).
# Usage:  .\database\local\init.ps1   (run from repo root or anywhere)
# Requires: MySQL Server 8.4 binaries (winget install Oracle.MySQL)

$ErrorActionPreference = 'Stop'
$Local   = $PSScriptRoot
$DataDir = Join-Path $Local 'data'
$Bin     = 'C:\Program Files\MySQL\MySQL Server 8.4\bin'
$Mysqld  = Join-Path $Bin 'mysqld.exe'

if (-not (Test-Path $Mysqld)) {
    Write-Error "mysqld.exe not found at $Bin. Install with: winget install Oracle.MySQL"
}
if (Test-Path (Join-Path $DataDir 'mysql')) {
    Write-Host "Data directory already initialised at $DataDir - nothing to do." -ForegroundColor Yellow
    exit 0
}

# Render my.ini with absolute forward-slash paths (MySQL on Windows prefers them).
$template = Get-Content (Join-Path $Local 'my.ini') -Raw
$ini      = $template.Replace('__DATADIR__', ($DataDir -replace '\\','/')).Replace('__LOCALDIR__', ($Local -replace '\\','/'))
$iniPath  = Join-Path $Local 'my.rendered.ini'
Set-Content -Path $iniPath -Value $ini -Encoding ascii

New-Item -ItemType Directory -Force -Path $DataDir | Out-Null

Write-Host "Initialising MySQL data directory (root@localhost with empty password, localhost only)..." -ForegroundColor Cyan
# mysqld logs to stderr; run via cmd so PowerShell 5.1 doesn't treat that as a terminating error.
$initLog = Join-Path $Local 'mysql-init.log'
cmd /c "`"$Mysqld`" --defaults-file=`"$iniPath`" --initialize-insecure --console > `"$initLog`" 2>&1"
if ($LASTEXITCODE -ne 0) {
    Get-Content $initLog | Select-Object -Last 15
    Write-Error "mysqld --initialize failed (exit $LASTEXITCODE). Full log: $initLog"
}
Get-Content $initLog | Select-String -Pattern '\[(ERROR|Warning)\]' | ForEach-Object { Write-Host $_ -ForegroundColor Yellow }

Write-Host ""
Write-Host "Done. Next:" -ForegroundColor Green
Write-Host "  1) .\database\local\start.ps1          # start the server (keep window open)"
Write-Host "  2) .\database\local\create-app-db.ps1  # create 'erp' database + app user, prints .env lines"
