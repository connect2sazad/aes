$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$phpExecutable = 'C:\xampp\php\php.exe'
$mysqlExecutable = 'C:\xampp\mysql\bin\mysqld.exe'
$databaseConfig = Join-Path $projectDirectory 'storage\testdb\my.ini'
$ownedDatabase = $null
if (-not (Test-Path -LiteralPath $phpExecutable)) { throw 'Install PHP or update the PHP executable path in this script.' }
try {
    if ((Test-Path -LiteralPath $databaseConfig) -and -not (Get-NetTCPConnection -State Listen -LocalPort 3307 -ErrorAction SilentlyContinue)) {
        $ownedDatabase = Start-Process -FilePath $mysqlExecutable -ArgumentList @("--defaults-file=`"$databaseConfig`"", '--bind-address=127.0.0.1') -WindowStyle Hidden -PassThru
        Start-Sleep -Seconds 2
        if ($ownedDatabase.HasExited) { throw 'The local database could not start. Check storage/testdb/*.err.' }
    }
    Write-Host 'Website: http://127.0.0.1:8080 | Admin: http://127.0.0.1:8080/admin.php'
    Write-Host 'Create an admin in a second terminal: C:\xampp\php\php.exe create-admin.php'
    Write-Host 'Press Ctrl+C to stop the local preview. Never expose this server publicly.'
    Push-Location -LiteralPath $projectDirectory
    try { & $phpExecutable -S 127.0.0.1:8080 router.php } finally { Pop-Location }
} finally {
    if ($ownedDatabase -and -not $ownedDatabase.HasExited) { Stop-Process -Id $ownedDatabase.Id }
}
