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

    curl -fsSL \
        "https://download.docker.com/linux/${DOCKER_DISTRO}/gpg" \
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
    RELEASE_BASE_URL="https://github.com/HiveDevelopment/hiveworker/releases/latest/download"
else
    RELEASE_BASE_URL="https://github.com/HiveDevelopment/hiveworker/releases/download/${WORKER_VERSION}"
fi

WORKER_ASSET="hiveworker_linux_${WORKER_ARCH}"
UPDATER_ASSET="hiveworker-updater_linux_${WORKER_ARCH}"

WORKER_DOWNLOAD_URL="${RELEASE_BASE_URL}/${WORKER_ASSET}"
UPDATER_DOWNLOAD_URL="${RELEASE_BASE_URL}/${UPDATER_ASSET}"
CHECKSUMS_DOWNLOAD_URL="${RELEASE_BASE_URL}/checksums.txt"

TEMP_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$TEMP_DIR"
}

trap cleanup EXIT

TEMP_WORKER="${TEMP_DIR}/${WORKER_ASSET}"
TEMP_UPDATER="${TEMP_DIR}/${UPDATER_ASSET}"
TEMP_CHECKSUMS="${TEMP_DIR}/checksums.txt"

download_file() {
    local url="$1"
    local destination="$2"
    local description="$3"

    log "Downloading ${description}..."

    if ! curl \
        -fL \
        --retry 3 \
        --retry-delay 2 \
        --connect-timeout 15 \
        "$url" \
        -o "$destination"; then

        fail "Failed to download ${description} from ${url}"
    fi
}

download_file \
    "$WORKER_DOWNLOAD_URL" \
    "$TEMP_WORKER" \
    "HivePanel Worker ${WORKER_VERSION}"

download_file \
    "$UPDATER_DOWNLOAD_URL" \
    "$TEMP_UPDATER" \
    "HivePanel Worker updater"

download_file \
    "$CHECKSUMS_DOWNLOAD_URL" \
    "$TEMP_CHECKSUMS" \
    "release checksums"

command -v sha256sum >/dev/null 2>&1 \
    || fail "sha256sum is required to verify HivePanel Worker downloads."

verify_checksum() {
    local asset="$1"
    local file="$2"

    local expected
    local actual

    expected="$(
        awk -v asset="$asset" \
            '$2 == asset || $2 == "*" asset { print $1; exit }' \
            "$TEMP_CHECKSUMS"
    )"

    if [[ -z "$expected" ]]; then
        fail "No SHA-256 checksum was published for ${asset}."
    fi

    actual="$(sha256sum "$file" | awk '{print $1}')"

    if [[ "$actual" != "$expected" ]]; then
        fail "SHA-256 verification failed for ${asset}."
    fi

    log "Verified ${asset}."
}

log "Verifying release files..."

verify_checksum \
    "$WORKER_ASSET" \
    "$TEMP_WORKER"

verify_checksum \
    "$UPDATER_ASSET" \
    "$TEMP_UPDATER"

chmod 0755 "$TEMP_WORKER"
chmod 0755 "$TEMP_UPDATER"

#
# Perform a basic sanity check on the downloaded Worker.
#
# HiveWorker does not currently expose a dedicated --version CLI
# command, so --help is used only to ensure that the binary can
# execute on this host.
#
if ! "$TEMP_WORKER" --help >/dev/null 2>&1; then
    fail "Downloaded HivePanel Worker binary could not be executed."
fi

log "Installing HivePanel Worker..."

install \
    -m 0755 \
    "$TEMP_WORKER" \
    /usr/local/bin/hiveworker

log "Installing Worker update helper..."

install \
    -m 0755 \
    "$TEMP_UPDATER" \
    /usr/local/libexec/hiveworker-updater

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
echo "  docker ps"

BASH;

        return response($script, 200, [
            'Content-Type' => 'text/x-shellscript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}