# Entorno Docker — AppSolicitudes

Tres servicios, tres imágenes: base de datos, API Laravel y cliente Flutter.

## Versiones fijadas

| Componente | Versión | Dónde se fija |
|------------|---------|---------------|
| PostgreSQL | 17-alpine | `docker-compose.yml` |
| PHP | 8.4 | `docker/laravel/Dockerfile` |
| Laravel | 13.x | `AppSolicitudesAdmin/composer.json` |
| Node | 22 | `docker/laravel/Dockerfile` (lo exige Vite 8) |
| Flutter | 3.41.9 | `.env` → `FLUTTER_VERSION` |
| Dart | 3.11.5 | incluido en el SDK de Flutter |

Flutter y Dart coinciden con lo que ya hay instalado en el host, para que el SDK
del contenedor y el local no produzcan resultados distintos.

## Primer arranque

```bash
cp .env.example .env
docker compose build
docker compose up -d
```

El `entrypoint` de Laravel es idempotente: copia `.env.docker.example` si hace
falta, instala dependencias, genera `APP_KEY`, espera a que PostgreSQL responda
y ejecuta las migraciones.

### Volúmenes nombrados y permisos

`vendor/` y `node_modules/` viven en volúmenes nombrados, que Docker crea como
`root`. Si el contenedor de Laravel entra en bucle de reinicio con
`Failed opening required '/var/www/html/vendor/autoload.php'`, corrige el dueño
una sola vez e instala las dependencias:

```bash
docker compose run --rm --no-deps --user root --entrypoint sh laravel   -c "chown -R app:app /var/www/html/vendor /var/www/html/node_modules"
docker compose run --rm --no-deps --entrypoint sh laravel -c "composer install"
docker compose up -d laravel
```

En Git Bash sobre Windows, los argumentos que empiezan por `/` se reescriben a
rutas de Windows; usa PowerShell o `sh -c "..."` como arriba.

## Puertos

| Servicio | URL |
|----------|-----|
| API / panel Laravel | http://localhost:8000 |
| Cliente Flutter web | http://localhost:8080 |
| Vite (assets) | http://localhost:5173 |
| PostgreSQL | localhost:5433 |

PostgreSQL se publica en 5433 y no en 5432 para no chocar con una instalación
local. Dentro de la red de Compose el puerto sigue siendo 5432.

## Comandos frecuentes

```bash
# Tests
docker compose exec laravel php artisan test
docker compose exec flutter flutter test

# Migraciones
docker compose exec laravel php artisan migrate
docker compose exec laravel php artisan migrate:fresh --seed

# Consola de PostgreSQL
docker compose exec postgres psql -U appsolicitudes -d appsolicitudes

# Estilo y análisis estático
docker compose exec laravel ./vendor/bin/pint
docker compose exec flutter flutter analyze

# Reinicio limpio (borra los datos de la base)
docker compose down -v && docker compose up -d --build
```

## Alcance del contenedor de Flutter

El contenedor sirve para `flutter test`, `flutter analyze` y para ejecutar la
app en modo web. **No** reemplaza al SDK del host para lo siguiente:

- Emulador de Android o dispositivo físico: Docker Desktop en Windows no expone
  USB ni KVM al contenedor.
- Compilación de iOS: requiere macOS y Xcode.

Para compilar un APK dentro del contenedor haría falta añadir el Android SDK a
`docker/flutter/Dockerfile` (unos 2 GB más de imagen). Conviene hacerlo solo
cuando se configure el workflow de build en CI, no para el desarrollo diario.

## Base de datos de las pruebas

`AppSolicitudesAdmin/phpunit.xml` fuerza (`force="true"`) `DB_CONNECTION=pgsql` y
`DB_DATABASE=appsolicitudes_test`. El `force` es necesario: el contenedor ya
define `DB_DATABASE=appsolicitudes` y, sin él, PHPUnit respetaría esa variable y
las pruebas con `RefreshDatabase` borrarían los datos de desarrollo.
