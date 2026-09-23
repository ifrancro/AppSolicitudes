# Panel web (Blade)

Interfaz web para el personal: `personal_administrativo`, `responsable` y `administrador`.
Los estudiantes usan la app móvil y no pueden entrar al panel (ni con sesión ni al iniciar sesión).

## Decisión: sesión y servicios compartidos, no la API por HTTP

El panel **no** consume `/api/v1` por HTTP. Usa sesión de Laravel y llama directamente a las mismas
clases que usa la API. Motivos:

1. **Una sola fuente de reglas de negocio.** Asignar, cambiar de estado, clasificar y registrar acciones
   viven en `App\Services\Solicitudes\*` (y los reportes en `App\Services\Reportes\ReportesService`).
   La API y el panel las invocan igual, así que no pueden divergir. Los servicios lanzan
   `ValidationException`; la API la convierte en 422 y el panel vuelve al formulario con los errores.
2. **Las mismas políticas.** El panel autoriza con `SolicitudPolicy`, `AdjuntoPolicy` y los gates
   (`ver-reportes`, `acceder-panel`). Una regla de acceso nueva se escribe una vez.
3. **Sin salto de red ni gestión de tokens.** Una llamada HTTP de la aplicación a sí misma duplicaría la
   autenticación (token en el servidor), añadiría latencia y complicaría los errores. Además el servidor de
   desarrollo (`artisan serve`) se bloquearía con peticiones anidadas si tuviera un solo proceso.
4. **Cuentas de navegador.** Sesión + CSRF es el mecanismo adecuado; los tokens Sanctum son para clientes móviles.

Coste asumido: hay controladores del panel además de los de la API, pero solo hacen de "pegamento"
(autorizar, validar, llamar al servicio, redirigir). Reutilizan los mismos `FormRequest`.

## Pantallas

| Ruta | Rol | Contenido |
|---|---|---|
| `/login` | público | Inicio de sesión (limitado a 5 intentos por minuto) |
| `/panel` | todos | Redirige: administrador → dashboard, responsable → sus asignadas, personal → listado |
| `/panel/dashboard` | administrador | Resumen, solicitudes por tipo y tiempos de atención, con filtro de fechas (HU-10) |
| `/panel/solicitudes` | personal, administrador | Listado global con filtros y paginación (HU-05) |
| `/panel/asignadas` | responsable | Solicitudes con asignación activa al usuario (HU-07) |
| `/panel/solicitudes/{id}` | según política `view` | Detalle, historial, asignaciones, acciones y evidencias |

En el detalle, cada formulario aparece solo si la política lo permite: clasificar y asignar/reasignar
(personal y administrador, HU-05/06), cambiar estado (destinos legales para el rol, HU-07) y registrar
acción (solo el responsable asignado, HU-08). Las evidencias se descargan por `/panel/adjuntos/{id}`
con la misma comprobación de permisos que la API; nunca hay una URL pública.

## Probarlo

```bash
docker compose exec laravel php artisan migrate:fresh --seed
```

Abrir <http://localhost:8000> e iniciar sesión (contraseña `password`):
`admin@campus.test`, `personal@campus.test` o `responsable@campus.test`.
`estudiante@campus.test` es rechazado a propósito.
