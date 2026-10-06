FROM composer:2 AS vendor

WORKDIR /build

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader


FROM node:22-alpine AS frontend

WORKDIR /build

COPY package.json package-lock.json ./

RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json tailwind.config.js components.json ./

COPY --from=vendor /build/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

RUN npm run build


FROM php:8.4-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libcurl4-openssl-dev \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd intl mbstring opcache pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /build /var/www/html
COPY --from=frontend /build/public/build /var/www/html/public/build

COPY docker/php/production.ini /usr/local/etc/php/conf.d/99-hivepanel.ini
COPY docker/scripts/entrypoint.sh /usr/local/bin/hivepanel-entrypoint
COPY docker/scripts/healthcheck.sh /usr/local/bin/hivepanel-healthcheck

RUN chmod +x /usr/local/bin/hivepanel-entrypoint /usr/local/bin/hivepanel-healthcheck \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["hivepanel-entrypoint"]

CMD ["php-fpm", "-F"]


FROM nginx:1.28-alpine AS nginx

COPY public /var/www/html/public
COPY --from=frontend /build/public/build /var/www/html/public/build

RUN ln -s /var/www/html/storage/app/public /var/www/html/public/storage

COPY docker/nginx/templates /etc/nginx/templates
COPY docker/nginx/entrypoint.sh /usr/local/bin/hivepanel-nginx-entrypoint

RUN chmod +x /usr/local/bin/hivepanel-nginx-entrypoint

ENTRYPOINT ["hivepanel-nginx-entrypoint"]