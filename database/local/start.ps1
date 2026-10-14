# Start the project-local MySQL server in the foreground (Ctrl+C to stop).
# Pass -Background to detach (use stop.ps1 to shut it down cleanly).
param([switch]$Background)

$ErrorActionPreference = 'Stop'
$Local   = $PSScriptRoot
$Bin     = 'C:\Program Files\MySQL\MySQL Server 8.4\bin'
$Mysqld  = Join-Path $Bin 'mysqld.exe'
$iniPath = Join-Path $Local 'my.rendered.ini'

if (-not (Test-Path $iniPath)) { Write-Error "Not initialised. Run .\database\local\init.ps1 first." }

# Already listening?
$listening = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Write-Host "Something is already listening on 127.0.0.1:3306 (PID $($listening[0].OwningProcess)). Assuming MySQL is up." -ForegroundColor Yellow
    exit 0
}

if ($Background) {
    $p = Start-Process -FilePath $Mysqld -ArgumentList "--defaults-file=`"$iniPath`"" -WindowStyle Hidden -PassThru
    Set-Content -Path (Join-Path $Local 'mysqld.pid') -Value $p.Id
    # wait for the port
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 500
        if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
            Write-Host "MySQL started in background (PID $($p.Id)) on 127.0.0.1:3306" -ForegroundColor Green
            exit 0
        }
    }
    Write-Error "MySQL did not start within 15s. See $Local\mysql-error.log"
} else {
    Write-Host "Starting MySQL on 127.0.0.1:3306 (Ctrl+C to stop)..." -ForegroundColor Cyan
    & $Mysqld --defaults-file="$iniPath" --console
}
