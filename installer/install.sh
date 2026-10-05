#!/usr/bin/env bash
set -euo pipefail

INSTALL_DIR="${HIVEPANEL_INSTALL_DIR:-/opt/hivepanel}"
SOURCE_URL="${HIVEPANEL_SOURCE_URL:-https://github.com/HiveDevelopment/HivePanel/archive/refs/heads/main.tar.gz}"

log() { printf '\033[1;33m[HivePanel]\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31m[HivePanel] ERROR:\033[0m %s\n' "$1" >&2; exit 1; }
random_hex() { openssl rand -hex "$1"; }

[[ "${EUID}" -eq 0 ]] || fail "Run this installer as root or with sudo."

command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v openssl >/dev/null 2>&1 || fail "openssl is required."
command -v tar >/dev/null 2>&1 || fail "tar is required."

printf '\nHivePanel Docker Installer\n\n'
read -r -p "Panel domain (for example panel.example.com): " DOMAIN
[[ -n "$DOMAIN" ]] || fail "A panel domain is required."
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN%%/*}"

read -r -p "Administrator name: " ADMIN_NAME
read -r -p "Administrator email: " ADMIN_EMAIL
read -r -s -p "Administrator password: " ADMIN_PASSWORD
printf '\n'
read -r -s -p "Confirm administrator password: " ADMIN_PASSWORD_CONFIRM
printf '\n'
[[ "$ADMIN_PASSWORD" == "$ADMIN_PASSWORD_CONFIRM" ]] || fail "Administrator passwords do not match."
[[ ${#ADMIN_PASSWORD} -ge 8 ]] || fail "Administrator password must be at least 8 characters."

read -r -p "Enable Let's Encrypt HTTPS? [Y/n]: " ENABLE_HTTPS
ENABLE_HTTPS="${ENABLE_HTTPS:-Y}"
CERT_EMAIL=""
APP_SCHEME="http"
if [[ "$ENABLE_HTTPS" =~ ^[Yy]$ ]]; then
    APP_SCHEME="https"
    read -r -p "Let's Encrypt email [$ADMIN_EMAIL]: " CERT_EMAIL
    CERT_EMAIL="${CERT_EMAIL:-$ADMIN_EMAIL}"
fi

install_docker() {
    if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
        log "Docker and Docker Compose are already installed."
        return
    fi

    log "Installing Docker Engine and Compose..."
    if command -v dnf >/dev/null 2>&1; then
        dnf -y install dnf-plugins-core curl ca-certificates
        dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo >/dev/null 2>&1 || true
        dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    elif command -v apt-get >/dev/null 2>&1; then
        apt-get update
        apt-get install -y ca-certificates curl gnupg
        install -m 0755 -d /etc/apt/keyrings
        . /etc/os-release
        case "${ID:-}" in ubuntu|debian) ;; *) fail "Unsupported apt-based distribution: ${ID:-unknown}" ;; esac
        curl -fsSL "https://download.docker.com/linux/${ID}/gpg" -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${ID} ${VERSION_CODENAME} stable" > /etc/apt/sources.list.d/docker.list
        apt-get update
        apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    else
        fail "Automatic Docker installation supports apt and dnf based Linux distributions."
    fi

    systemctl enable --now docker
    docker compose version >/dev/null 2>&1 || fail "Docker Compose plugin is unavailable after installation."
}

install_docker

if [[ -e "$INSTALL_DIR" && -n "$(ls -A "$INSTALL_DIR" 2>/dev/null || true)" ]]; then
    fail "$INSTALL_DIR is not empty. Move the existing installation or set HIVEPANEL_INSTALL_DIR."
fi

log "Downloading HivePanel..."
mkdir -p "$INSTALL_DIR"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
curl -fsSL --retry 3 "$SOURCE_URL" -o "$TMP_DIR/hivepanel.tar.gz"
tar -xzf "$TMP_DIR/hivepanel.tar.gz" -C "$TMP_DIR"
SOURCE_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
[[ -n "$SOURCE_DIR" ]] || fail "Downloaded HivePanel archive is invalid."
cp -a "$SOURCE_DIR"/. "$INSTALL_DIR"/
cd "$INSTALL_DIR"

APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
DB_PASSWORD="$(random_hex 24)"
DB_ROOT_PASSWORD="$(random_hex 32)"
REDIS_PASSWORD="$(random_hex 24)"

cat > .env <<ENV
APP_NAME=HivePanel
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=${APP_SCHEME}://${DOMAIN}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file
LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=hivepanel
DB_USERNAME=hivepanel
DB_PASSWORD=${DB_PASSWORD}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
MAIL_MAILER=log
MAIL_FROM_ADDRESS=${ADMIN_EMAIL}
MAIL_FROM_NAME=HivePanel

VITE_APP_NAME=HivePanel
BACKUP_MOUNT_LIFETIME_MINUTES=60
BACKUP_MAXIMUM_ACTIVE_MOUNTS=1

HIVEPANEL_DOMAIN=${DOMAIN}
HTTP_PORT=80
HTTPS_PORT=443
HIVEPANEL_VERSION=local
ENV
chmod 0600 .env

log "Building HivePanel containers..."
docker compose build --pull

log "Starting database and Redis..."
docker compose up -d mariadb redis

log "Starting HivePanel..."
docker compose up -d panel queue scheduler nginx

log "Running database migrations..."
docker compose exec -T panel php artisan migrate --force

docker compose exec -T panel php artisan optimize:clear
docker compose exec -T panel php artisan config:cache
docker compose exec -T panel php artisan route:cache
docker compose exec -T panel php artisan view:cache

log "Creating administrator..."
docker compose exec -T \
    -e HIVEPANEL_ADMIN_NAME="$ADMIN_NAME" \
    -e HIVEPANEL_ADMIN_EMAIL="$ADMIN_EMAIL" \
    -e HIVEPANEL_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    panel php artisan hivepanel:create-admin

if [[ "$ENABLE_HTTPS" =~ ^[Yy]$ ]]; then
    log "Requesting Let's Encrypt certificate..."
    if docker compose --profile tools run --rm certbot certonly \
        --webroot \
        --webroot-path /var/www/certbot \
        --domain "$DOMAIN" \
        --email "$CERT_EMAIL" \
        --agree-tos \
        --no-eff-email; then
        docker compose restart nginx
        log "HTTPS enabled."
    else
        log "Certificate request failed. Falling back to HTTP."
        sed -i "s#^APP_URL=.*#APP_URL=http://${DOMAIN}#" .env
        docker compose up -d --force-recreate panel queue scheduler
        docker compose exec -T panel php artisan config:cache
    fi
fi

log "Checking HivePanel health..."
for _ in $(seq 1 30); do
    if curl -fsS -H "Host: ${DOMAIN}" "http://127.0.0.1/up" >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

printf '\nHivePanel installation complete.\n\n'
if [[ "$ENABLE_HTTPS" =~ ^[Yy]$ ]] && docker compose exec -T nginx test -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" >/dev/null 2>&1; then
    printf 'Panel: https://%s\n' "$DOMAIN"
else
    printf 'Panel: http://%s\n' "$DOMAIN"
fi
printf 'Install directory: %s\n\n' "$INSTALL_DIR"
printf 'Useful commands:\n'
printf '  cd %s && docker compose ps\n' "$INSTALL_DIR"
printf '  cd %s && docker compose logs -f panel\n' "$INSTALL_DIR"
printf '  cd %s && docker compose logs -f queue\n\n' "$INSTALL_DIR"
