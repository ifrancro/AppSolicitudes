#!/usr/bin/env bash
# Arranque idempotente: seguro de ejecutar en cada `docker compose up`.
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
  echo "[entrypoint] .env ausente, copiando .env.docker.example"
  cp .env.docker.example .env
fi

if [ ! -d vendor ]; then
  echo "[entrypoint] Instalando dependencias de Composer"
  composer install --no-interaction --prefer-dist
fi

if [ ! -d node_modules ]; then
  echo "[entrypoint] Instalando dependencias de npm"
  npm install --no-audit --no-fund
fi

if ! grep -qE '^APP_KEY=base64:' .env; then
  echo "[entrypoint] Generando APP_KEY"
  php artisan key:generate --force
fi

echo "[entrypoint] Esperando a PostgreSQL en ${DB_HOST:-postgres}:${DB_PORT:-5432}"
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-appsolicitudes}" >/dev/null 2>&1; do
  sleep 1
done

echo "[entrypoint] Ejecutando migraciones"
php artisan migrate --force

exec "$@"
