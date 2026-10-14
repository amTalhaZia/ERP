# One-command local development start (Windows, no XAMPP).
#   .\start-dev.ps1            → MySQL (background) + API (:8000) + frontend (:3000) in separate windows
# Stop: close the two windows, then .\database\local\stop.ps1
$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

# Resolve PHP (winget install path or PATH)
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
    $php = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter php.exe -ErrorAction SilentlyContinue |
        Where-Object { $_.DirectoryName -like '*PHP.PHP.8.3*' } | Select-Object -First 1 -ExpandProperty FullName
}
if (-not $php) { Write-Error 'PHP 8.3 not found. Install with: winget install PHP.PHP.8.3' }

& "$root\database\local\start.ps1" -Background

Start-Process powershell -ArgumentList '-NoExit', '-Command', "Set-Location '$root\backend'; & '$php' artisan serve --host=127.0.0.1 --port=8000" -WindowStyle Normal
Start-Process powershell -ArgumentList '-NoExit', '-Command', "Set-Location '$root\frontend'; npm run dev" -WindowStyle Normal

Write-Host ''
Write-Host 'Starting…' -ForegroundColor Cyan
Write-Host '  API       http://localhost:8000/api/v1/health'
Write-Host '  Frontend  http://localhost:3000'
Write-Host '  DB GUI    http://localhost/phpmyadmin (XAMPP Apache) or DBeaver → 127.0.0.1:3306'
