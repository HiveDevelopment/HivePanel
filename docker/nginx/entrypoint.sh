#!/usr/bin/env sh
set -eu

DOMAIN="${HIVEPANEL_DOMAIN:-_}"
CERT="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
KEY="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"

if [ "$DOMAIN" != "_" ] && [ -f "$CERT" ] && [ -f "$KEY" ]; then
    sed "s/__DOMAIN__/${DOMAIN}/g" /etc/nginx/templates/https.conf.template > /etc/nginx/conf.d/default.conf
else
    sed "s/__DOMAIN__/${DOMAIN}/g" /etc/nginx/templates/http.conf.template > /etc/nginx/conf.d/default.conf
fi

exec nginx -g 'daemon off;'
