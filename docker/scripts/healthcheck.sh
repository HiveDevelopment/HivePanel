#!/usr/bin/env sh
set -eu
php-fpm -t >/dev/null 2>&1
php artisan about --only=environment >/dev/null 2>&1
