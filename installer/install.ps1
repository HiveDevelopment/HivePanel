#Requires -Version 7.0
param(
    [string]$InstallDirectory = "$env:ProgramData\HivePanel",
    [string]$Repository = 'HiveDevelopment/HivePanel',
    [string]$Version = ''
)

$ErrorActionPreference = 'Stop'
function Write-HivePanel([string]$Message) { Write-Host "[HivePanel] $Message" -ForegroundColor Yellow }
function New-HexSecret([int]$Bytes) { $data = New-Object byte[] $Bytes; [Security.Cryptography.RandomNumberGenerator]::Fill($data); [Convert]::ToHexString($data).ToLowerInvariant() }

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is required with Linux containers enabled.' }
docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose is unavailable.' }

if ([string]::IsNullOrWhiteSpace($Version)) {
    $release = Invoke-RestMethod -Headers @{ 'User-Agent' = 'HivePanel-Installer' } -Uri "https://api.github.com/repos/$Repository/releases/latest"
    $Version = ([string]$release.tag_name).TrimStart('v')
} else { $Version = $Version.TrimStart('v') }
if ($Version -notmatch '^\d+\.\d+\.\d+(-(alpha|beta|rc)(\.\d+)?)?$') { throw "Invalid HivePanel version: $Version" }
$Tag = "v$Version"

$Domain = (Read-Host 'Panel domain (for example panel.example.com)') -replace '^https?://', ''
$Domain = ($Domain -split '/')[0]
if ([string]::IsNullOrWhiteSpace($Domain)) { throw 'A panel domain is required.' }
$AdminName = Read-Host 'Administrator name'
$AdminEmail = Read-Host 'Administrator email'
$SecurePassword = Read-Host 'Administrator password' -AsSecureString
$Credential = [PSCredential]::new('hivepanel', $SecurePassword)
$AdminPassword = $Credential.GetNetworkCredential().Password
if ($AdminPassword.Length -lt 8) { throw 'Administrator password must be at least 8 characters.' }

if ((Test-Path $InstallDirectory) -and (Get-ChildItem -Force $InstallDirectory -ErrorAction SilentlyContinue)) { throw "$InstallDirectory is not empty." }
New-Item -ItemType Directory -Force -Path $InstallDirectory, (Join-Path $InstallDirectory 'runtime'), (Join-Path $InstallDirectory 'backups') | Out-Null
Set-Location $InstallDirectory
$RawBase = "https://raw.githubusercontent.com/$Repository/$Tag"
Invoke-WebRequest -Uri "$RawBase/compose.yaml" -OutFile (Join-Path $InstallDirectory 'compose.yaml')

$keyBytes = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Fill($keyBytes)
$AppKey = 'base64:' + [Convert]::ToBase64String($keyBytes)
$DbPassword = New-HexSecret 24; $DbRootPassword = New-HexSecret 32; $RedisPassword = New-HexSecret 24
$Environment = @"
HIVEPANEL_IMAGE=ghcr.io/hivedevelopment/hivepanel
HIVEPANEL_NGINX_IMAGE=ghcr.io/hivedevelopment/hivepanel-nginx
HIVEPANEL_VERSION=$Version
HIVEPANEL_REPOSITORY=$Repository
HIVEPANEL_UPDATE_REQUEST_PATH=/var/lib/hivepanel-host/update-request.json
HIVEPANEL_UPDATE_STATUS_PATH=/var/lib/hivepanel-host/update-status.json
PANEL_DOMAIN=$Domain
APP_NAME=HivePanel
APP_ENV=production
APP_KEY=$AppKey
APP_DEBUG=false
APP_URL=http://$Domain
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
HTTP_PORT=80
HTTPS_PORT=443
"@
[IO.File]::WriteAllText((Join-Path $InstallDirectory '.env'), $Environment)

Write-HivePanel "Pulling HivePanel v$Version..."
docker compose pull
docker compose up -d mariadb redis panel queue scheduler nginx
if ($LASTEXITCODE -ne 0) { throw 'HivePanel failed to start.' }
docker compose exec -T panel php artisan migrate --force
docker compose exec -T panel php artisan optimize
docker compose exec -T -e "HIVEPANEL_ADMIN_NAME=$AdminName" -e "HIVEPANEL_ADMIN_EMAIL=$AdminEmail" -e "HIVEPANEL_ADMIN_PASSWORD=$AdminPassword" panel php artisan hivepanel:create-admin

Write-HivePanel 'Installing the Windows update runner...'
$UpdaterPath = Join-Path $InstallDirectory 'hivepanel-update.ps1'
Invoke-WebRequest -Uri "$RawBase/installer/host/hivepanel-update.ps1" -OutFile $UpdaterPath
$taskName = 'HivePanel Update Runner'
$action = New-ScheduledTaskAction -Execute (Get-Command pwsh).Source -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$UpdaterPath`" -InstallDirectory `"$InstallDirectory`""
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null

Write-Host ''
Write-Host "HivePanel v$Version installation complete." -ForegroundColor Green
Write-Host "Panel: http://$Domain"
Write-Host "Install directory: $InstallDirectory"
Write-Host 'Configure HTTPS with your Windows reverse proxy or edge proxy before production use.'
