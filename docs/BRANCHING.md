# Estrategia de ramas — Monorepo AppSolicitudes

Este documento define cómo se trabaja con Git en el repositorio que contiene el
cliente Flutter (`appsolicitudes/`), el panel administrativo Laravel
(`AppSolicitudesAdmin/`) y la infraestructura Docker compartida (`docker/`).

El modelo es de **promoción por niveles**: el código asciende desde la rama
menos estable hasta la más estable, y nunca en sentido contrario.

```
feat/api/crear-solicitud ──┐
                           ├──> develop ──> test ──> main
feat/app/listado ──────────┘   integración    QA     entrega
```

## 1. Ramas permanentes

| Rama | Contiene | Recibe de | Protección |
|------|----------|-----------|------------|
| `develop` | Trabajo integrado del equipo. Puede fallar ocasionalmente. | Ramas de trabajo | PR obligatorio, CI rápida en verde |
| `test` | Candidata a entrega, en validación. | Solo de `develop` | PR obligatorio, suite completa en verde |
| `main` | Lo entregado. Siempre estable. | Solo de `test` (o de un hotfix) | PR obligatorio, aprobación, historial lineal |

Regla que sostiene todo el modelo: **nadie hace `push` directo a `test` ni a
`main`**. Si una de las dos recibe un commit que no vino de la rama inferior,
las tres se desincronizan y el modelo deja de dar garantías.

## 2. Ramas de trabajo (vida corta)

Formato: `<tipo>/<ámbito>/<descripción-corta>`, siempre creadas **desde
`develop`**.

**Tipos**

- `feat` — funcionalidad nueva
- `fix` — corrección de un defecto
- `refactor` — cambio interno sin alterar el comportamiento
- `test` — solo pruebas (habitual en XP cuando se escribe el test primero)
- `chore` — dependencias, configuración, mantenimiento
- `docs` — documentación

**Ámbitos** (imprescindibles en un monorepo: indican qué proyecto toca el cambio)

- `app` — Flutter (`appsolicitudes/`)
- `api` — Laravel (`AppSolicitudesAdmin/`)
- `db` — migraciones, seeders, esquema de PostgreSQL
- `infra` — Docker, CI, scripts de la raíz
- `full` — cambio que cruza cliente y servidor a la vez

**Ejemplos**

```
feat/api/endpoint-crear-solicitud
feat/app/pantalla-listado-solicitudes
fix/api/validacion-cedula-duplicada
feat/full/flujo-aprobacion-solicitud
chore/infra/subir-postgres-a-17
```

**Reglas de vida**

- Máximo 2 días. Si una rama vive más, la historia de usuario era demasiado
  grande y debe partirse.
- Rebase diario sobre `develop` para que los conflictos aparezcan pequeños.
- Se elimina en cuanto se fusiona.

## 3. Nivel 1 — Rama de trabajo hacia `develop`

```bash
git switch develop
git pull --rebase origin develop
git switch -c feat/api/endpoint-crear-solicitud

# ciclo XP: test que falla -> código mínimo -> refactor -> commit
docker compose exec laravel php artisan test
docker compose exec flutter flutter test

git push -u origin feat/api/endpoint-crear-solicitud
# abrir pull request contra develop
```

**Estrategia de fusión: squash merge.** Cada historia de usuario entra en
`develop` como un solo commit. El historial queda legible y revertir una
historia completa es un único `git revert`.

**Qué valida la CI aquí:** solo la suite del proyecto afectado, según las rutas
modificadas. Debe terminar rápido para no frenar la integración.

## 4. Nivel 2 — `develop` hacia `test`

Se promueve al cerrar un bloque de historias, normalmente al final del sprint o
cuando hay algo presentable.

```bash
git switch test
git pull origin test
git merge --no-ff develop
git push origin test
```

**Estrategia de fusión: merge commit (`--no-ff`).** El commit de merge deja
registrado qué bloque de historias entró en validación y cuándo.

**Qué valida la CI aquí:** la suite completa de ambos proyectos, migraciones
desde cero contra PostgreSQL, y `flutter analyze` + `pint` sin advertencias.

```bash
docker compose down -v && docker compose up -d
docker compose exec laravel php artisan migrate:fresh --seed
docker compose exec laravel php artisan test
docker compose exec flutter flutter test
```

**Si `test` falla:** el arreglo **no** se hace en `test`. Se crea
`fix/<ámbito>/<descripción>` desde `develop`, se corrige ahí, y se vuelve a
promover. Así `develop` nunca queda con un defecto que `test` ya resolvió.

## 5. Nivel 3 — `test` hacia `main`

Solo cuando la validación pasó y el bloque está aceptado.

```bash
git switch main
git pull origin main
git merge --no-ff test
git tag -a v0.3.0 -m "Entrega sprint 3"
git push origin main --follow-tags
```

Cada llegada a `main` se etiqueta. Una etiqueta por sprint o por entrega
académica. `main` no lleva commits propios: todo lo que contiene pasó antes por
`test`.

## 6. Corrección urgente sobre lo entregado

Excepción única al flujo ascendente, cuando `main` tiene un fallo que no puede
esperar al siguiente ciclo:

```bash
git switch -c hotfix/api/error-500-al-aprobar main
# primero el test que reproduce el fallo, después la corrección
git switch main
git merge --no-ff hotfix/api/error-500-al-aprobar
git tag -a v0.3.1 -m "Hotfix: error 500 al aprobar"
git push origin main --follow-tags
```

**Paso obligatorio después:** devolver la corrección hacia abajo, o el fallo
reaparece en la siguiente promoción.

```bash
git switch test
git merge --no-ff main
git push origin test

git switch develop
git merge --no-ff test
git push origin develop
```

## 7. Convención de commits

Conventional Commits, con el mismo ámbito de la rama:

```
feat(api): agregar endpoint POST /solicitudes
fix(app): corregir formato de fecha en el listado
test(api): cubrir validación de cédula duplicada
chore(infra): fijar PostgreSQL en 17-alpine
```

Permite filtrar el historial por proyecto:
`git log --oneline --grep "^feat(app)"`.

## 8. Protecciones a configurar en GitHub

`Settings → Branches → Add rule`, una regla por rama:

**`develop`**
- Require a pull request before merging.
- Require status checks: `test-laravel`, `test-flutter`.
- Automatically delete head branches.

**`test`**
- Require a pull request before merging.
- Require status checks: `test-laravel`, `test-flutter`, `test-integration`.
- Restrict who can push: solo merges desde `develop`.

**`main`**
- Require a pull request before merging, con 1 aprobación.
- Require status checks: todos los anteriores.
- Require branches to be up to date before merging.
- Restrict who can push.

## 9. Integración continua por rutas

Como el repositorio contiene dos proyectos, cada workflow se activa solo con los
cambios que le corresponden. Un cambio en Flutter no ejecuta la suite de
Laravel:

```yaml
# .github/workflows/test-laravel.yml
on:
  pull_request:
    branches: [develop, test, main]
    paths:
      - 'AppSolicitudesAdmin/**'
      - 'docker/laravel/**'
      - 'docker-compose.yml'

# .github/workflows/test-flutter.yml
on:
  pull_request:
    branches: [develop, test, main]
    paths:
      - 'appsolicitudes/**'
      - 'docker/flutter/**'
```

Las ramas con ámbito `full` tocan ambas rutas y disparan los dos workflows.

## 10. Creación inicial de las ramas

```bash
git switch -c develop main
git push -u origin develop
git switch -c test develop
git push -u origin test
```

Conviene fijar `develop` como rama por defecto del repositorio en GitHub
(`Settings → General → Default branch`), para que los pull requests apunten ahí
sin tener que cambiarlo a mano cada vez.

## 11. Qué nunca se sube

- `.env` de cualquiera de los dos proyectos (solo las plantillas `.example`).
- `vendor/`, `node_modules/`, `build/`, `.dart_tool/`.
- Claves de firma de Android (`*.keystore`, `key.properties`).
- El volumen de datos de PostgreSQL.

Los tres archivos `.gitignore` (raíz, `appsolicitudes/` y
`AppSolicitudesAdmin/`) ya cubren estos casos.
