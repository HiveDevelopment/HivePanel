#Requires -Version 7.0
param([string]$InstallDirectory = "$env:ProgramData\HivePanel")

$ErrorActionPreference = 'Stop'
$RequestFile = Join-Path $InstallDirectory 'runtime\update-request.json'
$StatusFile = Join-Path $InstallDirectory 'runtime\update-status.json'
if (-not (Test-Path $RequestFile)) { exit 0 }

function Set-UpdateStatus([string]$State, [string]$Version, [string]$Message, [string]$Backup = '') {
    $payload = @{ state = $State; version = $Version; message = $Message; backup = $Backup; updated_at = [DateTime]::UtcNow.ToString('o') } | ConvertTo-Json -Compress
    $temporary = "$StatusFile.tmp"
    [IO.File]::WriteAllText($temporary, $payload)
    Move-Item -Force $temporary $StatusFile
}

Set-Location $InstallDirectory
$request = Get-Content $RequestFile -Raw | ConvertFrom-Json
$Version = [string]$request.version
if ($Version -notmatch '^\d+\.\d+\.\d+(-(alpha|beta|rc)(\.\d+)?)?$') { Remove-Item $RequestFile -Force; exit 1 }

$envLines = Get-Content (Join-Path $InstallDirectory '.env')
$current = (($envLines | Where-Object { $_ -like 'HIVEPANEL_VERSION=*' }) -replace '^HIVEPANEL_VERSION=', '')
$rootPassword = (($envLines | Where-Object { $_ -like 'DB_ROOT_PASSWORD=*' }) -replace '^DB_ROOT_PASSWORD=', '')
$backupDir = Join-Path $InstallDirectory 'backups'
New-Item -ItemType Directory -Force $backupDir | Out-Null
$backup = Join-Path $backupDir ("hivepanel-{0}-{1}.sql" -f $current, (Get-Date -Format 'yyyyMMdd-HHmmss'))

try {
    Set-UpdateStatus 'backing_up' $Version 'Creating a database backup before updating.'
    docker compose exec -T mariadb mariadb-dump -uroot "-p$rootPassword" --single-transaction --quick --lock-tables=false hivepanel | Set-Content -Encoding utf8 $backup
    if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }

    Set-UpdateStatus 'pulling' $Version "Downloading HivePanel v$Version." $backup
    $env:HIVEPANEL_VERSION = $Version
    docker compose pull panel queue scheduler nginx
    if ($LASTEXITCODE -ne 0) { throw 'The new HivePanel containers could not be downloaded.' }

    docker compose exec -T panel php artisan down --retry=30 | Out-Null
    (Get-Content .env) -replace '^HIVEPANEL_VERSION=.*', "HIVEPANEL_VERSION=$Version" | Set-Content -Encoding utf8 .env

    Set-UpdateStatus 'restarting' $Version 'Starting the new HivePanel application container.' $backup
    docker compose up -d --no-deps panel
    if ($LASTEXITCODE -ne 0) { throw 'HivePanel could not start the new application container.' }

    Set-UpdateStatus 'migrating' $Version 'Running database migrations and rebuilding application caches.' $backup
    docker compose exec -T panel php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'A database migration failed.' }
    docker compose exec -T panel php artisan optimize:clear | Out-Null
    docker compose exec -T panel php artisan optimize | Out-Null

    Set-UpdateStatus 'restarting' $Version 'Restarting HivePanel services.' $backup
    docker compose up -d panel queue scheduler nginx
    if ($LASTEXITCODE -ne 0) { throw 'One or more HivePanel services failed to restart.' }
    docker compose exec -T panel php artisan up | Out-Null
    Remove-Item $RequestFile -Force
    Set-UpdateStatus 'complete' $Version "HivePanel was updated successfully to v$Version." $backup
} catch {
    Set-UpdateStatus 'failed' $Version $_.Exception.Message $backup
    exit 1
}
