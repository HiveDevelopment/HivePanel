#Requires -Version 7.0
param(
    [string]$InstallDirectory = "$env:ProgramData\HivePanel",
    [string]$SourceUrl = "https://github.com/HiveDevelopment/HivePanel/archive/refs/heads/main.zip"
)

$ErrorActionPreference = 'Stop'

function Write-HivePanel([string]$Message) {
    Write-Host "[HivePanel] $Message" -ForegroundColor Yellow
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker Desktop is required on Windows. Install Docker Desktop with Linux containers enabled, then run this installer again.'
}

docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose is unavailable. Update Docker Desktop and try again.'
}

$Domain = Read-Host 'Panel domain (for example panel.example.com)'
$Domain = $Domain -replace '^https?://', ''
$Domain = ($Domain -split '/')[0]
if ([string]::IsNullOrWhiteSpace($Domain)) {
    throw 'A panel domain is required.'
}

$AdminName = Read-Host 'Administrator name'
$AdminEmail = Read-Host 'Administrator email'
$SecurePassword = Read-Host 'Administrator password' -AsSecureString
$Credential = New-Object System.Management.Automation.PSCredential('hivepanel', $SecurePassword)
$AdminPassword = $Credential.GetNetworkCredential().Password
if ($AdminPassword.Length -lt 8) {
    throw 'Administrator password must be at least 8 characters.'
}

if (Test-Path $InstallDirectory) {
    $existing = Get-ChildItem -Force $InstallDirectory -ErrorAction SilentlyContinue
    if ($existing) {
        throw "$InstallDirectory is not empty."
    }
}

Write-HivePanel 'Downloading HivePanel...'
$TempDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ("hivepanel-" + [guid]::NewGuid())
New-Item -ItemType Directory -Force -Path $TempDirectory | Out-Null
$Archive = Join-Path $TempDirectory 'hivepanel.zip'
Invoke-WebRequest -Uri $SourceUrl -OutFile $Archive
Expand-Archive -Path $Archive -DestinationPath $TempDirectory
$SourceDirectory = Get-ChildItem $TempDirectory -Directory | Select-Object -First 1
New-Item -ItemType Directory -Force -Path $InstallDirectory | Out-Null
Copy-Item -Path (Join-Path $SourceDirectory.FullName '*') -Destination $InstallDirectory -Recurse -Force
Set-Location $InstallDirectory

function New-HexSecret([int]$Bytes) {
    $data = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($data)
    return [Convert]::ToHexString($data).ToLowerInvariant()
}

$keyBytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Fill($keyBytes)
$AppKey = 'base64:' + [Convert]::ToBase64String($keyBytes)
$DbPassword = New-HexSecret 24
$DbRootPassword = New-HexSecret 32
$RedisPassword = New-HexSecret 24

$Environment = @"
APP_NAME=HivePanel
APP_ENV=production
APP_KEY=$AppKey
APP_DEBUG=false
APP_URL=https://$Domain
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=daily
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=hivepanel
DB_USERNAME=hivepanel
DB_PASSWORD=$DbPassword
DB_ROOT_PASSWORD=$DbRootPassword
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=$RedisPassword
REDIS_PORT=6379
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=$AdminEmail
MAIL_FROM_NAME=HivePanel
VITE_APP_NAME=HivePanel
BACKUP_MOUNT_LIFETIME_MINUTES=60
BACKUP_MAXIMUM_ACTIVE_MOUNTS=1
HIVEPANEL_DOMAIN=$Domain
HTTP_PORT=80
HTTPS_PORT=443
HIVEPANEL_VERSION=local
"@
[System.IO.File]::WriteAllText((Join-Path $InstallDirectory '.env'), $Environment)

Write-HivePanel 'Building HivePanel containers...'
docker compose build --pull
if ($LASTEXITCODE -ne 0) { throw 'HivePanel container build failed.' }

docker compose up -d mariadb redis panel queue scheduler nginx
if ($LASTEXITCODE -ne 0) { throw 'HivePanel failed to start.' }

docker compose exec -T panel php artisan migrate --force
docker compose exec -T panel php artisan optimize:clear
docker compose exec -T panel php artisan config:cache
docker compose exec -T panel php artisan route:cache
docker compose exec -T panel php artisan view:cache

docker compose exec -T -e "HIVEPANEL_ADMIN_NAME=$AdminName" -e "HIVEPANEL_ADMIN_EMAIL=$AdminEmail" -e "HIVEPANEL_ADMIN_PASSWORD=$AdminPassword" panel php artisan hivepanel:create-admin

Write-Host ''
Write-Host 'HivePanel installation complete.' -ForegroundColor Green
Write-Host "Panel: http://$Domain"
Write-Host "Install directory: $InstallDirectory"
Write-Host ''
Write-Host 'For public Windows hosting, configure HTTPS using your reverse proxy or run the Linux deployment on a server/VM. The same HivePanel containers are used on both platforms.'

Remove-Item -Recurse -Force $TempDirectory -ErrorAction SilentlyContinue
