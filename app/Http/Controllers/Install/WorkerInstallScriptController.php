<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class WorkerInstallScriptController extends Controller
{
    public function show(): Response
    {
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

PANEL_URL=""
REGISTRATION_TOKEN=""
WORKER_VERSION="latest"

log() {
    echo "[HivePanel] $1"
}

fail() {
    echo "[HivePanel] ERROR: $1" >&2
    exit 1
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --panel-url)
            [[ $# -ge 2 ]] || fail "Missing value for --panel-url"
            PANEL_URL="$2"
            shift 2
            ;;
        --token)
            [[ $# -ge 2 ]] || fail "Missing value for --token"
            REGISTRATION_TOKEN="$2"
            shift 2
            ;;
        --version)
            [[ $# -ge 2 ]] || fail "Missing value for --version"
            WORKER_VERSION="$2"
            shift 2
            ;;
        *)
            fail "Unknown argument: $1"
            ;;
    esac
done

[[ "$EUID" -eq 0 ]] || fail "Please run this installer as root or with sudo."
[[ -n "$PANEL_URL" ]] || fail "Missing --panel-url"
[[ -n "$REGISTRATION_TOKEN" ]] || fail "Missing --token"

PANEL_URL="${PANEL_URL%/}"

case "$PANEL_URL" in
    http://*|https://*)
        ;;
    *)
        fail "Panel URL must begin with http:// or https://"
        ;;
esac

if [[ "$REGISTRATION_TOKEN" != hpreg_* ]]; then
    fail "The supplied registration token is not a valid HivePanel registration token."
fi

ARCH="$(uname -m)"

case "$ARCH" in
    x86_64|amd64)
        WORKER_ARCH="amd64"
        ;;
    aarch64|arm64)
        WORKER_ARCH="arm64"
        ;;
    *)
        fail "Unsupported architecture: $ARCH"
        ;;
esac

log "Installing HivePanel Worker..."
log "Architecture: ${WORKER_ARCH}"

install_base_packages() {
    if command -v dnf >/dev/null 2>&1; then
        log "Installing required packages..."
        dnf -y install curl ca-certificates dnf-plugins-core
        return
    fi

    if command -v yum >/dev/null 2>&1; then
        log "Installing required packages..."
        yum -y install curl ca-certificates
        return
    fi

    if command -v apt-get >/dev/null 2>&1; then
        log "Installing required packages..."
        export DEBIAN_FRONTEND=noninteractive
        apt-get update
        apt-get install -y curl ca-certificates
        return
    fi

    fail "Unsupported Linux distribution. HivePanel currently requires a dnf, yum or apt based distribution."
}

install_docker_rhel() {
    log "Installing Docker..."

    if command -v dnf >/dev/null 2>&1; then
        dnf -y install dnf-plugins-core

        if [[ ! -f /etc/yum.repos.d/docker-ce.repo ]]; then
            dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
        fi

        dnf -y install \
            docker-ce \
            docker-ce-cli \
            containerd.io \
            docker-buildx-plugin \
            docker-compose-plugin

        return
    fi

    yum -y install yum-utils

    if [[ ! -f /etc/yum.repos.d/docker-ce.repo ]]; then
        yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    fi

    yum -y install \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin
}

install_docker_debian() {
    log "Installing Docker..."

    export DEBIAN_FRONTEND=noninteractive

    apt-get update
    apt-get install -y ca-certificates curl

    install -m 0755 -d /etc/apt/keyrings

    . /etc/os-release

    case "${ID:-}" in
        ubuntu)
            DOCKER_DISTRO="ubuntu"
            ;;
        debian)
            DOCKER_DISTRO="debian"
            ;;
        *)
            fail "Automatic Docker installation is not supported for this apt-based distribution."
            ;;
    esac

    curl -fsSL "https://download.docker.com/linux/${DOCKER_DISTRO}/gpg" \
        -o /etc/apt/keyrings/docker.asc

    chmod a+r /etc/apt/keyrings/docker.asc

    echo \
        "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${DOCKER_DISTRO} ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list

    apt-get update

    apt-get install -y \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin
}

install_docker() {
    if command -v docker >/dev/null 2>&1; then
        log "Docker is already installed."
        return
    fi

    if command -v dnf >/dev/null 2>&1 || command -v yum >/dev/null 2>&1; then
        install_docker_rhel
        return
    fi

    if command -v apt-get >/dev/null 2>&1; then
        install_docker_debian
        return
    fi

    fail "Unable to install Docker automatically on this distribution."
}

install_base_packages
install_docker

log "Enabling Docker..."

systemctl enable --now docker

if ! systemctl is-active --quiet docker; then
    fail "Docker failed to start. Check 'systemctl status docker' for more information."
fi

if ! docker info >/dev/null 2>&1; then
    fail "Docker is installed but the Docker daemon is not responding."
fi

log "Preparing HivePanel directories..."

install -d -m 0755 /etc/hivepanel
install -d -m 0700 /etc/hivepanel/keys

install -d -m 0755 /var/lib/hivepanel
install -d -m 0755 /var/lib/hivepanel/data
install -d -m 0755 /var/lib/hivepanel/cells
install -d -m 0755 /var/lib/hivepanel/backups
install -d -m 0755 /var/lib/hivepanel/backup_mounts
install -d -m 0755 /var/lib/hivepanel/updates

install -d -m 0755 /usr/local/libexec

if docker network inspect hivepanel >/dev/null 2>&1; then
    log "Docker network 'hivepanel' already exists."
else
    log "Creating Docker network 'hivepanel'..."
    docker network create hivepanel >/dev/null
fi

log "Writing bootstrap configuration..."

cat > /etc/hivepanel/worker.yml <<YAML
panel:
  url: "${PANEL_URL}"

worker:
  registration_token: "${REGISTRATION_TOKEN}"
YAML

chmod 0600 /etc/hivepanel/worker.yml

if [[ "$WORKER_VERSION" == "latest" ]]; then
    DOWNLOAD_URL="https://github.com/HiveDevelopment/hiveworker/releases/latest/download/hiveworker_linux_${WORKER_ARCH}"
else
    DOWNLOAD_URL="https://github.com/HiveDevelopment/hiveworker/releases/download/${WORKER_VERSION}/hiveworker_linux_${WORKER_ARCH}"
fi

log "Downloading HivePanel Worker ${WORKER_VERSION}..."

TEMP_BINARY="$(mktemp)"

cleanup() {
    rm -f "$TEMP_BINARY"
}

trap cleanup EXIT

if ! curl \
    -fL \
    --retry 3 \
    --retry-delay 2 \
    "$DOWNLOAD_URL" \
    -o "$TEMP_BINARY"; then

    fail "Failed to download HivePanel Worker from ${DOWNLOAD_URL}"
fi

chmod +x "$TEMP_BINARY"

if ! "$TEMP_BINARY" --help >/dev/null 2>&1; then
    log "Worker binary downloaded successfully."
fi

install -m 0755 "$TEMP_BINARY" /usr/local/bin/hiveworker

log "Installing Worker update helper..."

cat > /usr/local/libexec/hiveworker-updater <<'UPDATER'
#!/usr/bin/env bash
set -u

PID=""
STAGED=""
TARGET=""

BINARY="/usr/local/bin/hiveworker"
BACKUP="/usr/local/bin/hiveworker.rollback"
SERVICE="hiveworker"
LOG="/var/log/hiveworker-updater.log"

log() {
    echo "[$(date -Is)] $1"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --pid)
            [[ $# -ge 2 ]] || exit 2
            PID="$2"
            shift 2
            ;;
        --staged)
            [[ $# -ge 2 ]] || exit 2
            STAGED="$2"
            shift 2
            ;;
        --target)
            [[ $# -ge 2 ]] || exit 2
            TARGET="$2"
            shift 2
            ;;
        *)
            exit 2
            ;;
    esac
done

touch "$LOG"
chmod 0600 "$LOG"

exec >>"$LOG" 2>&1

log "HiveWorker updater started."
log "Target version: ${TARGET}"

if [[ -z "$PID" || -z "$STAGED" || -z "$TARGET" ]]; then
    log "Missing updater arguments."
    exit 2
fi

if [[ ! "$PID" =~ ^[0-9]+$ ]]; then
    log "Invalid Worker PID."
    exit 2
fi

case "$STAGED" in
    /var/lib/hivepanel/updates/*)
        ;;
    *)
        log "Refusing staged binary outside the HivePanel update directory."
        exit 2
        ;;
esac

if [[ ! -f "$STAGED" ]]; then
    log "Staged Worker binary does not exist: ${STAGED}"
    exit 3
fi

if [[ ! -x "$STAGED" ]]; then
    log "Staged Worker binary is not executable."
    exit 3
fi

log "Waiting for Worker process ${PID} to exit..."

for _ in $(seq 1 30); do
    if ! kill -0 "$PID" 2>/dev/null; then
        break
    fi

    sleep 1
done

if kill -0 "$PID" 2>/dev/null; then
    log "Worker did not exit within 30 seconds. Stopping service."

    systemctl stop "$SERVICE" || true

    for _ in $(seq 1 10); do
        if ! kill -0 "$PID" 2>/dev/null; then
            break
        fi

        sleep 1
    done
fi

if kill -0 "$PID" 2>/dev/null; then
    log "Worker process is still running. Aborting update."
    exit 4
fi

log "Backing up current Worker binary..."

rm -f "$BACKUP"

if [[ -f "$BINARY" ]]; then
    if ! cp -a "$BINARY" "$BACKUP"; then
        log "Failed to back up current Worker binary."
        exit 5
    fi
fi

rollback() {
    log "Rolling back Worker update..."

    systemctl stop "$SERVICE" || true

    if [[ ! -f "$BACKUP" ]]; then
        log "Rollback binary is unavailable."
        return 1
    fi

    if ! install -m 0755 "$BACKUP" "$BINARY"; then
        log "Failed to restore previous Worker binary."
        return 1
    fi

    if ! systemctl restart "$SERVICE"; then
        log "Previous Worker binary was restored but the service failed to restart."
        return 1
    fi

    for _ in $(seq 1 30); do
        if systemctl is-active --quiet "$SERVICE"; then
            log "Previous Worker restored successfully."
            rm -f "$BACKUP"
            rm -f "$STAGED"
            return 0
        fi

        sleep 1
    done

    log "Previous Worker was restored but did not become active."
    return 1
}

log "Installing Worker ${TARGET}..."

if ! install -m 0755 "$STAGED" "$BINARY"; then
    log "Failed to install new Worker binary."

    rollback || true
    exit 6
fi

log "Starting Worker ${TARGET}..."

if ! systemctl restart "$SERVICE"; then
    log "systemd failed to restart the new Worker."

    rollback || true
    exit 7
fi

log "Waiting for Worker service..."

SERVICE_STARTED=0

for _ in $(seq 1 30); do
    if systemctl is-active --quiet "$SERVICE"; then
        SERVICE_STARTED=1
        break
    fi

    sleep 1
done

if [[ "$SERVICE_STARTED" -ne 1 ]]; then
    log "New Worker did not become active."

    journalctl \
        -u "$SERVICE" \
        -n 50 \
        --no-pager \
        || true

    rollback || true
    exit 8
fi

#
# Give the daemon a little time after systemd reports it active.
#
# The Panel heartbeat will perform the authoritative confirmation
# that the Worker returned with the requested version.
#
sleep 3

if ! systemctl is-active --quiet "$SERVICE"; then
    log "New Worker exited shortly after startup."

    journalctl \
        -u "$SERVICE" \
        -n 50 \
        --no-pager \
        || true

    rollback || true
    exit 9
fi

log "Worker ${TARGET} installed successfully."

rm -f "$BACKUP"
rm -f "$STAGED"

exit 0
UPDATER

chmod 0755 /usr/local/libexec/hiveworker-updater

log "Installing systemd service..."

cat > /etc/systemd/system/hiveworker.service <<'SERVICE'
[Unit]
Description=HivePanel Worker
Documentation=https://hivepanel.dev
After=network-online.target docker.service
Wants=network-online.target docker.service
Requires=docker.service

[Service]
Type=simple
User=root
Group=root
WorkingDirectory=/var/lib/hivepanel
ExecStart=/usr/local/bin/hiveworker --config /etc/hivepanel/worker.yml
Restart=always
RestartSec=5
TimeoutStopSec=30
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable hiveworker

log "Starting HivePanel Worker..."

if ! systemctl restart hiveworker; then
    journalctl -u hiveworker -n 30 --no-pager || true
    fail "HivePanel Worker failed to start."
fi

sleep 2

if ! systemctl is-active --quiet hiveworker; then
    journalctl -u hiveworker -n 30 --no-pager || true
    fail "HivePanel Worker did not remain running after startup."
fi

log "HivePanel Worker installed successfully."

echo
echo "Panel:        ${PANEL_URL}"
echo "Architecture: ${WORKER_ARCH}"
echo "Config:       /etc/hivepanel/worker.yml"
echo "Binary:       /usr/local/bin/hiveworker"
echo "Updater:      /usr/local/libexec/hiveworker-updater"
echo
echo "The worker has started and will register itself with HivePanel."
echo
echo "Useful commands:"
echo "  systemctl status hiveworker"
echo "  journalctl -u hiveworker -f"
echo "  tail -f /var/log/hiveworker-updater.log"
echo "  docker ps"
BASH;

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}