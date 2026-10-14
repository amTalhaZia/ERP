# Cleanly shut down the project-local MySQL server.
$ErrorActionPreference = 'Stop'
$Bin = 'C:\Program Files\MySQL\MySQL Server 8.4\bin'
& (Join-Path $Bin 'mysqladmin.exe') -h 127.0.0.1 -P 3306 -u root shutdown
if ($LASTEXITCODE -eq 0) { Write-Host "MySQL stopped." -ForegroundColor Green } else { Write-Warning "mysqladmin shutdown returned $LASTEXITCODE (server may not be running)." }
Remove-Item (Join-Path $PSScriptRoot 'mysqld.pid') -ErrorAction SilentlyContinue
