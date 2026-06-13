# syntax=docker/dockerfile:1.7
# -----------------------------------------------------------------------------
# A1 SMS Gateway — Laravel 12 API image
# Build context is the repo root; all COPY paths are root-relative.
# Single image, role decided by $CONTAINER_ROLE at runtime:
#   api        → php artisan serve on :8000
#   scheduler  → php artisan schedule:work
#   worker     → php artisan queue:work
#   horizon    → php artisan horizon
# -----------------------------------------------------------------------------

FROM php:8.3-cli-alpine AS base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apk add --no-cache \
        bash curl git icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
        freetype-dev oniguruma-dev postgresql-dev openssh-client tzdata jq \
    && apk add --no-cache --virtual .build-deps autoconf g++ make linux-headers \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) bcmath gd intl opcache pcntl pdo pdo_pgsql sockets zip \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Resolve PHP deps first so subsequent source edits don't bust the cache.
COPY api/composer.json api/composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader || true

COPY api/ .

RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/api/php.ini       /usr/local/etc/php/conf.d/zz-a1sms.ini
COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --retries=5 --start-period=20s \
  CMD curl -fsS http://127.0.0.1:8000/api/v1/health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["api"]
