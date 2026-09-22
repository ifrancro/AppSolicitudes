# Estrategia de ramas — Monorepo AppSolicitudes

Este documento define cómo se trabaja con Git en el repositorio que contiene el
cliente Flutter (`appsolicitudes/`), el panel administrativo Laravel
(`AppSolicitudesAdmin/`) y la infraestructura Docker compartida (`docker/`).

El modelo elegido es **trunk-based development con ramas de vida corta**. Es el
que mejor encaja con Extreme Programming: integración continua real, lotes
pequeños y `main` siempre desplegable. Se descarta GitFlow porque sus ramas
`develop` y `release` de larga duración generan merges grandes y conflictos
frecuentes, justo lo que XP busca evitar.

## 1. Ramas permanentes

| Rama   | Propósito | Protección |
|--------|-----------|------------|
| `main` | Única rama de larga duración. Siempre debe estar en verde y ser desplegable. | Push directo bloqueado. Requiere pull request, CI en verde y una aprobación. |

No existe `develop`. La integración ocurre en `main` varias veces al día.

## 2. Ramas de trabajo (vida corta)

Formato: `<tipo>/<ámbito>/<descripción-corta>`

**Tipos**

- `feat` — funcionalidad nueva
- `fix` — corrección de un defecto
- `refactor` — cambio interno sin alterar el comportamiento
- `test` — solo pruebas (habitual en XP cuando se escribe el test antes)
- `chore` — dependencias, configuración, tareas de mantenimiento
- `docs` — documentación

**Ámbitos** (imprescindibles en un monorepo, porque indican qué proyecto toca el cambio)

- `app` — Flutter (`appsolicitudes/`)
- `api` — Laravel (`AppSolicitudesAdmin/`)
- `db` — migraciones, seeders, esquema de PostgreSQL
- `infra` — Docker, CI, scripts de la raíz
- `full` — cambio que cruza cliente y servidor a la vez (por ejemplo, un
  endpoint nuevo y la pantalla que lo consume)

**Ejemplos**

```
feat/api/endpoint-crear-solicitud
feat/app/pantalla-listado-solicitudes
fix/api/validacion-cedula-duplicada
feat/full/flujo-aprobacion-solicitud
chore/infra/subir-postgres-a-17
```

**Reglas de vida**

- Máximo 2 días de vida. Si una rama vive más, la historia de usuario era
  demasiado grande y debe partirse.
- Rebase diario sobre `main` (`git pull --rebase origin main`) para que los
  conflictos aparezcan pequeños y temprano.
- Se elimina en cuanto se fusiona.

## 3. Flujo de trabajo diario

```bash
git switch main
git pull --rebase origin main
git switch -c feat/api/endpoint-crear-solicitud

# ciclo XP: test que falla -> código mínimo -> refactor -> commit
docker compose exec laravel php artisan test
docker compose exec flutter flutter test

git push -u origin feat/api/endpoint-crear-solicitud
# abrir pull request contra main
```

Al fusionar se usa **squash merge**: cada historia de usuario entra en `main`
como un único commit. El historial queda legible y revertir una historia
completa es un solo `git revert`.

## 4. Convención de commits

Se usa Conventional Commits, con el mismo ámbito de la rama:

```
feat(api): agregar endpoint POST /solicitudes
fix(app): corregir formato de fecha en el listado
test(api): cubrir validación de cédula duplicada
chore(infra): fijar PostgreSQL en 17-alpine
```

El beneficio práctico es que el ámbito permite filtrar el historial por
proyecto: `git log --oneline --grep "^feat(app)"`.

## 5. Etiquetas de versión

Las entregas se marcan con etiquetas en `main`, no con ramas de release:

```bash
git tag -a v0.3.0 -m "Entrega sprint 3"
git push origin v0.3.0
```

Se recomienda una etiqueta por cierre de sprint o por entrega académica.

## 6. Corrección urgente

Si algo se rompe en una versión ya entregada:

1. Crear `fix/<ámbito>/<descripción>` desde el tag afectado.
2. Escribir primero el test que reproduce el fallo.
3. Fusionar a `main` y etiquetar el parche (`v0.3.1`).

## 7. Protecciones a configurar en GitHub

En `Settings → Branches → Add rule` para `main`:

- Require a pull request before merging (1 aprobación).
- Require status checks to pass: `test-laravel`, `test-flutter`.
- Require branches to be up to date before merging.
- Require linear history (coherente con squash merge).
- Automatically delete head branches.

## 8. Integración continua por rutas

Como el repositorio contiene dos proyectos, cada workflow debe activarse solo
con los cambios que le corresponden. Así una modificación de Flutter no ejecuta
la suite de Laravel:

```yaml
# .github/workflows/test-laravel.yml
on:
  pull_request:
    paths:
      - 'AppSolicitudesAdmin/**'
      - 'docker/laravel/**'
      - 'docker-compose.yml'

# .github/workflows/test-flutter.yml
on:
  pull_request:
    paths:
      - 'appsolicitudes/**'
      - 'docker/flutter/**'
```

Para las ramas con ámbito `full` se disparan ambos workflows, porque el cambio
toca las dos rutas.

## 9. Qué nunca se sube

- `.env` de cualquiera de los dos proyectos (solo las plantillas `.example`).
- `vendor/`, `node_modules/`, `build/`, `.dart_tool/`.
- Claves de firma de Android (`*.keystore`, `key.properties`).
- El volumen de datos de PostgreSQL.

Los tres archivos `.gitignore` (raíz, `appsolicitudes/` y
`AppSolicitudesAdmin/`) ya cubren estos casos.
