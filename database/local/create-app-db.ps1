# Create the application database and a least-privilege app user on the local server.
# Prints the DB_* lines to paste into backend/.env. Safe to re-run (idempotent).
param(
    [string]$Database = 'erp',
    [string]$User     = 'erp_app',
    [string]$Password
)

$ErrorActionPreference = 'Stop'
$Bin   = 'C:\Program Files\MySQL\MySQL Server 8.4\bin'
$Mysql = Join-Path $Bin 'mysql.exe'

if (-not $Password) {
    $bytes = New-Object byte[] 24
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $Password = ([Convert]::ToBase64String($bytes) -replace '[^A-Za-z0-9]', '').Substring(0, 24)
}

$sql = @"
CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS ``${Database}_test`` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS '$User'@'localhost' IDENTIFIED BY '$Password';
ALTER USER '$User'@'localhost' IDENTIFIED BY '$Password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER ON ``$Database``.* TO '$User'@'localhost';
GRANT ALL PRIVILEGES ON ``${Database}_test``.* TO '$User'@'localhost';
FLUSH PRIVILEGES;
"@

$sql | & $Mysql -h 127.0.0.1 -P 3306 -u root
if ($LASTEXITCODE -ne 0) { Write-Error "Failed to create database/user. Is the server running? (.\database\local\start.ps1)" }

Write-Host ""
Write-Host "Database '$Database' and '${Database}_test' ready. Paste into backend/.env:" -ForegroundColor Green
Write-Host ""
Write-Host "DB_CONNECTION=mysql"
Write-Host "DB_HOST=127.0.0.1"
Write-Host "DB_PORT=3306"
Write-Host "DB_DATABASE=$Database"
Write-Host "DB_USERNAME=$User"
Write-Host "DB_PASSWORD=$Password"
Write-Host "DB_SSL_CA="
Write-Host ""
