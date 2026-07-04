# syntax=docker/dockerfile:1

# =====================================================================
# Stage 1 — build PHP dependencies with Composer
# =====================================================================
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-interaction \
        --prefer-dist --optimize-autoloader --ignore-platform-reqs

# =====================================================================
# Stage 2 — runtime: Apache + PHP 8.2
# =====================================================================
FROM php:8.2-apache AS runtime

# --- System libraries for the PHP extensions we compile ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
        libzip-dev libicu-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Apache: rewrite + headers + deflate (GZIP), DocumentRoot -> public/ ---
# `deflate` is required for the GZIP block in public/.htaccess to take effect;
# without it the <IfModule mod_deflate.c> guard silently skips compression.
RUN a2enmod rewrite headers deflate
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/favilla.ini

WORKDIR /var/www/html

# --- Application code + vendored dependencies ---
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

# --- Writable runtime directories (also backed by named volumes at runtime) ---
RUN mkdir -p \
        storage/sessions storage/logs storage/cache storage/tmp \
        storage/backups storage/imports storage/module_exports storage/reports \
        public/uploads \
    && chown -R www-data:www-data storage public/uploads \
    && chmod -R 775 storage public/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
