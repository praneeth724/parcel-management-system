# syntax=docker/dockerfile:1
#
# Production image for Render (or any Docker host).
# Build this in the cloud — you do NOT need Docker installed locally.
#
# Stage 1 compiles the Bootstrap/Vite assets with Node.
# Stage 2 is the PHP 8.3 runtime (nginx + PHP-FPM) that actually serves the app.

# ---------------------------------------------------------------------------
# Stage 1 — build front-end assets
# ---------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

# Install JS dependencies first so this layer is cached unless they change.
COPY package.json package-lock.json ./
RUN npm ci

# Compile resources/scss + resources/js into public/build.
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — PHP runtime
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

# System libraries needed by the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        nginx \
        supervisor \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && update-ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        bcmath \
        intl \
        exif \
        opcache \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official image rather than curl-installed).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (cached unless composer.json/lock change).
# --no-dev drops PHPUnit/Pint/etc; Faker was moved to require so seeding works.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Application code, then the compiled assets from stage 1.
COPY . .
COPY --from=assets /app/public/build ./public/build

# Finish the autoloader now that all source is present.
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Laravel needs to write to storage and bootstrap/cache.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Runtime configuration.
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render provides $PORT (default 10000); nginx is templated to it at start-up.
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
