# syntax=docker/dockerfile:1.7
# ---------------------------------------------------------------
# Imagen 1: API / Panel administrativo (Laravel 13, PHP 8.4)
# Incluye Node 22 porque Laravel 13 ejecuta Vite desde `artisan dev`.
# ---------------------------------------------------------------
FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    PHP_CLI_SERVER_WORKERS=4

# Extensiones PHP: pgsql para PostgreSQL, el resto son requisitos de Laravel.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        gd \
        exif \
        pcntl \
        opcache \
        redis \
    && rm -rf /tmp/*

# Utilidades del sistema + Node 22 (Vite 8 requiere Node >= 20).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev postgresql-client ca-certificates curl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Usuario no root con el mismo UID del host para no romper permisos en storage/.
RUN groupadd -g ${GID} app || true \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash app || true

WORKDIR /var/www/html

COPY docker/laravel/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER app

EXPOSE 8000 5173

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
