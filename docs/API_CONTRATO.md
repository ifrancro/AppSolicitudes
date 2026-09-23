# Contrato de la API REST — Campus Connect

Contrato **definitivo** de la API `/api/v1`. Sustituye a cualquier forma
"tolerante" que el cliente móvil aceptara hasta ahora: el servidor responde
siempre con la forma descrita aquí y el cliente puede dejar de probar
alternativas.

Estado: **fijado en la Fase C**. Lo implementan las fases C a J; un cambio en
este documento es un cambio de contrato y requiere avisar al cliente.

- Laravel 13 (PHP 8.4 en el contenedor), autenticación con Sanctum (token *bearer*).
- 27 endpoints, una sola API para móvil y web. Sin rutas duplicadas por plataforma.

## 1. Convenciones generales

| Tema | Regla |
|---|---|
| Base URL | `http://<host>:8000/api/v1` (emulador Android: `http://10.0.2.2:8000/api/v1`) |
| Formato | JSON UTF-8. Enviar siempre `Accept: application/json` |
| Autenticación | `Authorization: Bearer <token>` en todo salvo `POST /auth/login` y `GET /ping` |
| Identificadores | Enteros (`int`), nunca cadenas |
| Fechas | ISO 8601 con desfase, siempre en UTC: `2026-09-24T20:37:08+00:00` |
| Nombres JSON | `snake_case`, en español para el dominio (`titulo`, `estado_id`) |
| Nulos | Un campo sin valor viaja como `null`; no se omite (salvo relaciones opcionales, ver §1.2) |
| Prefijo de rutas | `/auth`, `/solicitudes`… sin `/api/v1` en esta tabla; se antepone siempre |

### 1.1 Envoltorios de respuesta

- **Un recurso**: `{"data": { ... }}`
- **Una colección corta y no paginada** (catálogos, historial, adjuntos, acciones,
  asignaciones): `{"data": [ ... ]}`
- **Una colección paginada**: ver §1.3.
- **Excepción, solo `POST /auth/login`**: `{"token": "...", "usuario": { ... }}` (sin `data`).
- **Excepción, solo `POST /auth/logout`**: `{"message": "Sesión cerrada."}`.
- **Agregados** (dashboard y reportes): `{"data": { ... }}` o `{"data": [ ... ]}`.

### 1.2 Relaciones anidadas

Los recursos incluyen objetos pequeños en lugar de solo ids, para evitar
peticiones extra:

- `tipo`: `{id, nombre, descripcion}`
- `prioridad`: `{id, nombre, nivel}`
- `estado`: `{id, nombre}`
- Personas (`estudiante`, `responsable`, `autor`, `asignado_por`): `{id, nombre}`

`estudiante` aparece en el detalle y en los endpoints web, y **no** en
`GET /mis-solicitudes` ni en la respuesta de `POST /solicitudes` (es el propio
usuario). `responsable` es `null` si la solicitud no tiene asignación activa.

### 1.3 Paginación

Endpoints paginados: `GET /mis-solicitudes`, `GET /solicitudes`,
`GET /solicitudes-asignadas`, `GET /notificaciones`, `GET /reportes/solicitudes`.

Parámetros de consulta:

| Parámetro | Por defecto | Regla |
|---|---|---|
| `page` | `1` | entero ≥ 1 |
| `per_page` | `15` | entero entre 1 y 50; valores fuera de rango dan 422 |

Respuesta (formato nativo de Laravel):

```json
{
  "data": [ { "...": "..." } ],
  "links": {
    "first": "http://localhost:8000/api/v1/mis-solicitudes?page=1",
    "last": "http://localhost:8000/api/v1/mis-solicitudes?page=3",
    "prev": null,
    "next": "http://localhost:8000/api/v1/mis-solicitudes?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "path": "http://localhost:8000/api/v1/mis-solicitudes",
    "per_page": 15,
    "to": 15,
    "total": 40
  }
}
```

Una página vacía devuelve `"data": []` con `total: 0`, nunca un error.
`meta.links` (lista de enlaces de página) también viaja en `meta`; el cliente
puede ignorarla. `links.next` es `null` en la última página: es la señal para
dejar de cargar.

### 1.4 Ordenación

Los listados de solicitudes aceptan `orden`: `created_at`, `-created_at`
(por defecto), `updated_at`, `-updated_at`. El prefijo `-` es descendente.
Otro valor da 422.

### 1.5 Errores

Todo error lleva `message` (texto en español, apto para mostrar al usuario).
Solo el 422 añade `errors`, en el formato nativo de Laravel; su `message` es el
primer error (más «(y N errores más)» si hay varios).

```json
{ "message": "El campo título es obligatorio. (y 1 error más)", "errors": { "titulo": ["El campo título es obligatorio."], "descripcion": ["El campo descripción es obligatorio."] } }
```

`errors` es `{campo: [mensajes]}`; los nombres de campo son los del cuerpo de
la petición (`email`, `titulo`, `archivo`…). Un campo puede traer varios mensajes.

| Código | Cuándo | `message` |
|---|---|---|
| **401** | Falta el token, es inválido/expirado/revocado, o el usuario fue desactivado. **Solo** "sesión inválida": el cliente borra el token y va al login | `No autenticado.` / `Tu cuenta está desactivada.` |
| **403** | Sesión válida pero sin permiso: rol incorrecto o recurso ajeno | `No tienes permiso para realizar esta acción.` |
| **404** | Ruta o recurso inexistente | `Recurso no encontrado.` |
| **405** | Método HTTP no permitido en la ruta | `Método no permitido.` |
| **422** | Validación de campos o regla de negocio violada (p. ej. transición de estado ilegal) | `errors` con el campo afectado |
| **429** | Demasiados intentos de login (5 por minuto por correo e IP) | `Demasiados intentos. Espera un momento e inténtalo de nuevo.` |
| **500** | Error interno; el cuerpo no revela detalles | `Server Error` |

Reglas de decisión:

- **401 nunca significa "sin permiso"**: eso es 403.
- **403 antes que 404 para recursos ajenos**: `GET /solicitudes/15` de una
  solicitud que existe pero es de otro estudiante da 403; si no existe, 404.
- **Credenciales incorrectas en login = 422**, no 401 (ver `POST /auth/login`).
- Una regla de negocio incumplida (transición ilegal, asignar a quien no es
  responsable, adjuntar a una solicitud cerrada) es **422** con el campo o
  `message` explicativo; **no** es 409 ni 400.

## 2. Roles

Nombres exactos, idénticos a `roles.nombre` y al enum `Rol` del cliente:

`estudiante` · `personal_administrativo` · `responsable` · `administrador`

Un mismo endpoint puede devolver datos distintos según el rol. Resumen de
quién puede llamar a cada endpoint (✔ permitido; el resto recibe 403):

| Endpoint | estudiante | personal_administrativo | responsable | administrador |
|---|:-:|:-:|:-:|:-:|
| `POST /auth/login`, `GET /ping` | público | | | |
| `POST /auth/logout`, `GET /auth/me` | ✔ | ✔ | ✔ | ✔ |
| `GET /tipos-solicitud`, `/prioridades`, `/estados-solicitud` | ✔ | ✔ | ✔ | ✔ |
| `POST /solicitudes` | ✔ | | | |
| `GET /mis-solicitudes` | ✔ | | | |
| `GET /solicitudes/{id}` | dueño | ✔ | si la tiene asignada | ✔ |
| `GET /solicitudes/{id}/historial-estados` | dueño | ✔ | si la tiene asignada | ✔ |
| `POST /solicitudes/{id}/adjuntos` | dueño | | | |
| `GET /solicitudes/{id}/adjuntos`, `GET /adjuntos/{id}/archivo` | dueño | ✔ | si la tiene asignada | ✔ |
| `GET /notificaciones`, `PATCH /notificaciones/{id}/leer` | propias | propias | propias | propias |
| `GET /solicitudes` | | ✔ | | ✔ |
| `PATCH /solicitudes/{id}/clasificacion` | | ✔ | | ✔ |
| `GET /usuarios/responsables` | | ✔ | | ✔ |
| `POST /solicitudes/{id}/asignaciones` | | ✔ | | ✔ |
| `GET /solicitudes/{id}/asignaciones` | | ✔ | si la tiene asignada | ✔ |
| `GET /solicitudes-asignadas` | | | ✔ | |
| `PATCH /solicitudes/{id}/estado` | | ✔ | si la tiene asignada (ver §6.7) | ✔ |
| `POST /solicitudes/{id}/acciones` | | | si la tiene asignada | |
| `GET /solicitudes/{id}/acciones` | | ✔ | si la tiene asignada | ✔ |
| `GET /dashboard/resumen`, `GET /reportes/*` | | | | ✔ |

"Asignada" significa **asignación activa** (`asignaciones.activo = true`). Al
reasignar, el responsable anterior pierde el acceso de inmediato.

## 3. Modelos JSON

### Usuario

```json
{ "id": 7, "nombre": "Ana Pérez", "email": "ana@campus.test", "rol": { "id": 1, "nombre": "estudiante" } }
```

`rol` es siempre un **objeto**. Nunca se envía `password_hash`.

### Solicitud

```json
{
  "id": 15,
  "titulo": "Proyector dañado",
  "descripcion": "El proyector del aula 204 no enciende.",
  "ubicacion": "Edificio B - Aula 204",
  "tipo": { "id": 2, "nombre": "soporte_tecnologico", "descripcion": "Problemas con equipos, redes, cuentas o software." },
  "prioridad": { "id": 2, "nombre": "media", "nivel": 2 },
  "estado": { "id": 1, "nombre": "pendiente" },
  "estudiante": { "id": 7, "nombre": "Ana Pérez" },
  "responsable": null,
  "created_at": "2026-09-24T20:37:08+00:00",
  "updated_at": "2026-09-24T20:37:08+00:00"
}
```

`ubicacion` puede ser `null`. `responsable` es `{id, nombre}` o `null`.

### Catálogos (valores sembrados)

- **tipos**: `mantenimiento`, `soporte_tecnologico`, `infraestructura`, `equipamiento`, `otros`
- **prioridades** (`nivel`): `baja` (1), `media` (2), `alta` (3), `urgente` (4)
- **estados**: `pendiente`, `asignada`, `en_proceso`, `cerrada`, `cancelada`

Toda solicitud nace `pendiente` con prioridad `media`; el personal la reclasifica.

## 4. Endpoints compartidos

### 4.1 `GET /ping`

Público. `200` → `{"status": "ok", "app": "AppSolicitudes", "timestamp": "2026-09-24T20:37:08+00:00"}`

### 4.2 `POST /auth/login`

Público. Limitado a 5 intentos por minuto por correo e IP.

Petición:

```json
{ "email": "estudiante@campus.test", "password": "password", "device_name": "pixel-7" }
```

`email` y `password` obligatorios; `device_name` opcional (máx. 100), nombra el token.

- **200**
  ```json
  { "token": "1|kP3x...", "usuario": { "id": 7, "nombre": "Estudiante Demo", "email": "estudiante@campus.test", "rol": { "id": 1, "nombre": "estudiante" } } }
  ```
  `token` va en la raíz y `usuario` también. Expira a los 30 días.
- **422** credenciales incorrectas (o correo inexistente; mismo mensaje para no revelar cuentas):
  `{"message": "...", "errors": {"email": ["Las credenciales no son correctas."]}}`.
  También 422 si faltan campos (`errors.email`, `errors.password`).
- **403** credenciales correctas pero cuenta desactivada: `{"message": "Tu cuenta está desactivada."}`
- **429** demasiados intentos.

> **Por qué 422 y no 401**: el cliente trata todo 401 como "sesión expirada" y
> borra el token. Un login fallido no es una sesión que expiró.

### 4.3 `POST /auth/logout`

Revoca el token usado en la petición. Sin cuerpo.

- **200** `{"message": "Sesión cerrada."}`
- **401** sin token válido.

### 4.4 `GET /auth/me`

- **200** `{"data": { <Usuario> }}`
- **401** sin token válido, o cuenta desactivada tras el login.

### 4.5 `GET /tipos-solicitud`

Todos los roles. **200** `{"data": [ {"id": 1, "nombre": "mantenimiento", "descripcion": "..."} ]}`

### 4.6 `GET /prioridades`

Todos los roles. **200** `{"data": [ {"id": 1, "nombre": "baja", "nivel": 1} ]}` ordenado por `nivel`.

### 4.7 `GET /estados-solicitud`

Todos los roles. **200** `{"data": [ {"id": 1, "nombre": "pendiente"} ]}` ordenado por `id`
(que es el orden natural del flujo).

### 4.8 `GET /solicitudes/{id}`

Detalle. Autorización por rol según §2.

- **200** `{"data": { <Solicitud> }}`
- **403** solicitud ajena (estudiante), no asignada (responsable) o rol sin acceso.
- **404** no existe.

## 5. Móvil — estudiante

### 5.1 `POST /solicitudes` (HU-01)

```json
{ "tipo_id": 2, "titulo": "Proyector dañado", "descripcion": "No enciende.", "ubicacion": "Edificio B - Aula 204" }
```

| Campo | Regla |
|---|---|
| `tipo_id` | obligatorio, entero, existe en `tipos_solicitud` |
| `titulo` | obligatorio, texto, máx. 150 |
| `descripcion` | obligatorio, texto, máx. 2000 |
| `ubicacion` | opcional, texto, máx. 255 |

El estudiante **no** envía prioridad ni estado (se ignoran si llegan).

- **201** `{"data": { <Solicitud> }}` con `estado.nombre = "pendiente"`, `prioridad.nombre = "media"`, sin `estudiante`.
- **422** validación. **403** si el rol no es estudiante.

### 5.2 `GET /mis-solicitudes` (HU-01)

Paginado (§1.3). Solo las solicitudes del usuario autenticado.

Filtros opcionales: `estado_id`, `tipo_id`, `q` (busca en el título, sin distinguir mayúsculas),
`desde` y `hasta` (`YYYY-MM-DD`, sobre `created_at`, ambos inclusivos), `orden` (§1.4).

- **200** colección paginada de `<Solicitud>` (sin `estudiante`).
- **403** si el rol no es estudiante. **422** filtros inválidos.

### 5.3 `GET /solicitudes/{id}/historial-estados` (HU-03)

Bitácora de cambios de estado, del más antiguo al más reciente. No paginado.

```json
{ "data": [
  { "id": 1, "estado": { "id": 1, "nombre": "pendiente" }, "comentario": "Solicitud registrada", "autor": { "id": 7, "nombre": "Ana Pérez" }, "created_at": "2026-09-24T20:37:08+00:00" },
  { "id": 2, "estado": { "id": 2, "nombre": "asignada" }, "comentario": null, "autor": { "id": 3, "nombre": "Admin Demo" }, "created_at": "2026-09-24T21:00:00+00:00" }
] }
```

- **200**, **403** sin acceso a la solicitud, **404**.

### 5.4 `POST /solicitudes/{id}/adjuntos` (HU-02)

`multipart/form-data` con un solo campo de archivo llamado **`archivo`**.

**Límites** (definitivos):

| Límite | Valor |
|---|---|
| Tamaño máximo por archivo | 5 MB (5120 KB) |
| Tipos permitidos | `image/jpeg` (`.jpg`, `.jpeg`), `image/png` (`.png`), `application/pdf` (`.pdf`) |
| Adjuntos por solicitud | 5 |
| Estados que admiten adjuntos | `pendiente`, `asignada`, `en_proceso` |

El tipo se valida por el **contenido** del archivo, no por la extensión.
Los archivos se guardan en almacenamiento privado; nunca hay una URL pública.

- **201**
  ```json
  { "data": { "id": 4, "solicitud_id": 15, "nombre": "3f2a1c9e.jpg", "tipo_archivo": "image/jpeg",
              "subido_por": { "id": 7, "nombre": "Ana Pérez" },
              "url": "http://localhost:8000/api/v1/adjuntos/4/archivo", "created_at": "2026-09-24T20:40:00+00:00" } }
  ```
  `url` apunta al endpoint de descarga autorizada (§5.6): requiere el token.
- **422** `errors.archivo`: falta el archivo, tipo no permitido, más de 5 MB o ya hay 5 adjuntos.
  Si la solicitud está `cerrada` o `cancelada`: 422 con `errors.archivo`.
- **403** si no es el estudiante dueño. **404**.

### 5.5 `GET /solicitudes/{id}/adjuntos` (HU-02)

**200** `{"data": [ <Adjunto> ]}` del más antiguo al más reciente. **403**, **404**.

### 5.6 `GET /adjuntos/{id}/archivo` (HU-02)

Descarga autorizada: exige el token y que el usuario pueda ver la solicitud.

- **200** cuerpo binario; `Content-Type` real del archivo; `Content-Disposition: inline; filename="..."`;
  `Cache-Control: private, no-store`.
- **403** sin acceso. **404** adjunto inexistente o archivo ausente en disco.

### 5.7 `GET /notificaciones` (HU-04)

Paginado (§1.3), solo las del usuario autenticado, más recientes primero.
Filtro opcional: `leido` (`0` o `1`).

```json
{ "data": [ { "id": 9, "solicitud_id": 15, "mensaje": "Tu solicitud «Proyector dañado» ahora está en proceso.", "leido": false, "created_at": "2026-09-24T22:00:00+00:00" } ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "...": "...", "total": 3, "no_leidas": 2 } }
```

`meta.no_leidas` es el total de no leídas del usuario, independiente del filtro y de la página.
`solicitud_id` puede ser `null`.

### 5.8 `PATCH /notificaciones/{id}/leer` (HU-04)

Sin cuerpo. Idempotente: marcar una ya leída no falla.

- **200** `{"data": { <Notificacion> }}` con `leido: true`.
- **403** si la notificación es de otro usuario. **404**.

## 6. Web — personal administrativo, responsable, administrador

### 6.1 `GET /solicitudes` (HU-05)

Listado global, paginado. Roles: `personal_administrativo`, `administrador`.

Filtros opcionales: `estado_id`, `tipo_id`, `prioridad_id`, `responsable_id`,
`estudiante_id`, `sin_asignar` (`1`: sin asignación activa), `q` (título),
`desde`, `hasta`, `orden`.

- **200** colección paginada de `<Solicitud>` con `estudiante` y `responsable`.
- **403** otros roles. **422** filtros inválidos.

### 6.2 `PATCH /solicitudes/{id}/clasificacion` (HU-05)

```json
{ "tipo_id": 3, "prioridad_id": 4 }
```

Al menos uno de los dos; ambos deben existir. Roles: `personal_administrativo`, `administrador`.
No se puede clasificar una solicitud `cerrada` o `cancelada`.

- **200** `{"data": { <Solicitud> }}`.
- **422** `errors.tipo_id` / `errors.prioridad_id` si no existe o no se envió ninguno de los dos; `message` si la solicitud está `cerrada` o `cancelada`.
- **403**, **404**.

### 6.3 `GET /usuarios/responsables` (HU-06)

Usuarios con rol `responsable` y `activo = true`. Roles: `personal_administrativo`, `administrador`.

```json
{ "data": [ { "id": 5, "nombre": "Responsable Demo", "email": "responsable@campus.test", "asignaciones_activas": 3 } ] }
```

Orden: menos carga primero (`asignaciones_activas` ascendente), luego nombre.

### 6.4 `POST /solicitudes/{id}/asignaciones` (HU-06)

```json
{ "responsable_id": 5, "comentario": "Urgente, revisar hoy" }
```

`comentario` opcional (máx. 500).
Roles: `personal_administrativo`, `administrador`.

Efectos, en una sola transacción:

1. Si hay una asignación activa, se cierra: `activo = false` y `fecha_fin = ahora` (no se borra).
2. Se crea la nueva asignación con `activo = true`.
3. Si la solicitud estaba `pendiente`, pasa a `asignada` y se registra en el historial de estados.

- **201**
  ```json
  { "data": { "id": 12, "solicitud_id": 15, "responsable": { "id": 5, "nombre": "Responsable Demo" },
              "asignado_por": { "id": 3, "nombre": "Admin Demo" }, "activo": true,
              "fecha_asignacion": "2026-09-24T21:00:00+00:00", "fecha_fin": null } }
  ```
- **422**:
  - `errors.responsable_id`: no existe, no tiene rol `responsable`, está desactivado, o ya es el responsable activo.
  - `message`: la solicitud está `cerrada` o `cancelada`.
- **403**, **404**.

### 6.5 `GET /solicitudes/{id}/asignaciones` (HU-06)

Historial completo, la más reciente primero. Incluye la activa y las anteriores.
Roles: `personal_administrativo`, `administrador` y el responsable que la tiene asignada.

**200** `{"data": [ <Asignacion> ]}`. **403**, **404**.

### 6.6 `GET /solicitudes-asignadas` (HU-07)

Paginado. Solo solicitudes con **asignación activa al usuario autenticado**. Rol: `responsable`.

Filtros opcionales: `estado_id`, `prioridad_id`, `q`, `orden`.

- **200** colección paginada de `<Solicitud>` con `estudiante`.
- **403** otros roles (incluido `administrador`: usa `GET /solicitudes`).

### 6.7 `PATCH /solicitudes/{id}/estado` (HU-07)

```json
{ "estado_id": 3, "comentario": "Iniciamos la revisión" }
```

`estado_id` obligatorio. `comentario` opcional (máx. 500), **obligatorio** si el nuevo estado es `cancelada`.

**Transiciones legales** (cualquier otra da 422):

| Desde | Hacia |
|---|---|
| `pendiente` | `asignada` (solo con `POST /solicitudes/{id}/asignaciones`), `cancelada` |
| `asignada` | `en_proceso`, `cancelada` |
| `en_proceso` | `cerrada`, `cancelada` |
| `cerrada` | ninguna (estado final) |
| `cancelada` | ninguna (estado final) |

Por este endpoint solo se puede pasar a `en_proceso`, `cerrada` o `cancelada`.

**Quién puede pedir cada destino**:

- `personal_administrativo` y `administrador`: los tres destinos legales.
- `responsable` con la solicitud asignada: `en_proceso` y `cerrada`. Pedir `cancelada` da 403.

Cada cambio escribe una fila en el historial de estados (con autor y comentario) y
genera una notificación al estudiante (§7).

- **200** `{"data": { <Solicitud> }}`.
- **422** `errors.estado_id`: estado inexistente, igual al actual, transición ilegal
  o destino no permitido por este endpoint (`pendiente`, `asignada`); `errors.comentario`
  si falta al cancelar.
- **403**, **404**.

### 6.8 `POST /solicitudes/{id}/acciones` (HU-08)

```json
{ "descripcion": "Se reemplazó la lámpara del proyector." }
```

`descripcion` obligatoria, máx. 1000. Rol: `responsable` con la solicitud asignada.
Solo si la solicitud está `asignada` o `en_proceso`.

- **201** `{"data": { "id": 21, "solicitud_id": 15, "responsable": { "id": 5, "nombre": "Responsable Demo" }, "descripcion": "...", "created_at": "2026-09-24T22:10:00+00:00" }}`
- **422** `errors.descripcion`, o `message` si la solicitud está en otro estado.
- **403**, **404**.

### 6.9 `GET /solicitudes/{id}/acciones` (HU-08)

Historial de acciones, la más reciente primero. Roles: `personal_administrativo`,
`administrador` y el responsable que la tiene asignada. **200** `{"data": [ <Accion> ]}`. **403**, **404**.

### 6.10 `GET /dashboard/resumen` (HU-10)

Rol: `administrador`.

```json
{ "data": {
  "total": 120,
  "sin_asignar": 8,
  "por_estado": { "pendiente": 8, "asignada": 20, "en_proceso": 30, "cerrada": 55, "cancelada": 7 },
  "por_prioridad": { "baja": 10, "media": 70, "alta": 30, "urgente": 10 },
  "por_tipo": { "mantenimiento": 30, "soporte_tecnologico": 40, "infraestructura": 20, "equipamiento": 20, "otros": 10 },
  "cerradas_ultimos_30_dias": 12,
  "tiempo_promedio_atencion_horas": 36.5
} }
```

Todas las claves de `por_estado`, `por_prioridad` y `por_tipo` aparecen siempre (con `0` si no hay).
`sin_asignar` cuenta las `pendiente` sin asignación activa.
`tiempo_promedio_atencion_horas` es `null` si aún no hay solicitudes cerradas.
**403** otros roles.

### 6.11 `GET /reportes/solicitudes` (HU-10)

Reporte consolidado, paginado. Rol: `administrador`.

Filtros: `desde`, `hasta` (sobre `created_at`), `estado_id`, `tipo_id`, `prioridad_id`, `orden`.

Cada elemento es una `<Solicitud>` con `estudiante`, `responsable` y un campo extra
`cerrada_at` (fecha ISO o `null`).

### 6.12 `GET /reportes/solicitudes-por-tipo` (HU-10)

Rol: `administrador`. Filtros: `desde`, `hasta`.

```json
{ "data": [ { "tipo_id": 1, "tipo": "mantenimiento", "total": 30,
              "por_estado": { "pendiente": 2, "asignada": 4, "en_proceso": 6, "cerrada": 17, "cancelada": 1 } } ] }
```

Un elemento por tipo del catálogo (con `total: 0` si no hay), en el orden de `id`.

### 6.13 `GET /reportes/tiempos-atencion` (HU-10)

Rol: `administrador`. Filtros: `desde`, `hasta` (sobre la fecha de creación de la solicitud).

El tiempo de atención es el transcurrido entre la creación de la solicitud y su
paso a `cerrada`, en horas con dos decimales. Solo cuentan solicitudes `cerradas`.

```json
{ "data": { "total_cerradas": 55, "promedio_horas": 36.5, "minimo_horas": 1.25, "maximo_horas": 120.0,
            "por_tipo": [ { "tipo_id": 1, "tipo": "mantenimiento", "total_cerradas": 17, "promedio_horas": 40.1 } ] } }
```

Sin cerradas: `total_cerradas: 0`, `promedio_horas`, `minimo_horas` y `maximo_horas` en `null`.
`por_tipo` incluye todos los tipos del catálogo (`promedio_horas: null` si no hay cerradas).

Los tres reportes: **403** para otros roles, **422** si `desde` > `hasta` o el formato de fecha es inválido.

## 7. Notificaciones generadas por el sistema

Cada cambio de estado de una solicitud crea una notificación para el **estudiante**
dueño con el mensaje:

`Tu solicitud «{titulo}» ahora está {estado}.`

donde `{estado}` es el nombre con espacios en lugar de guiones bajos
(`pendiente`, `asignada`, `en proceso`, `cerrada`, `cancelada`). No se notifica
a quien hizo el cambio si es el propio estudiante.

## 8. Cambios recomendados en el cliente móvil

El servidor ya cumple este contrato; el cliente puede simplificarse:

1. **Token**: leer solo `token` en la raíz de la respuesta de login (quitar `access_token`, `plainTextToken` y la búsqueda dentro de `data`).
2. **Usuario**: en login leer `usuario`; en `/auth/me` leer `data`. Quitar la prueba de `user` y de la raíz.
3. **Rol**: objeto `{id, nombre}`; quitar la rama de "rol como cadena".
4. **Colecciones**: siempre `{data: [...]}`; quitar la lista pelada en la raíz.
5. **Paginación**: usar `links.next == null` para detectar la última página.
6. **401 de login**: el servidor ya no lo usa para credenciales incorrectas (§4.2), así que
   el interceptor no confundirá un login fallido con una sesión expirada.
7. **`API_BASE_URL`** en `docker-compose.yml` no incluye `/api/v1`; el valor por defecto de `Env.baseUrl` sí.
8. Un rol desconocido debería tratarse como error de sesión en vez de `Rol.desconocido` silencioso.

## 9. Credenciales de prueba (solo desarrollo)

`php artisan migrate:fresh --seed` crea un usuario por rol, contraseña `password`:

| Rol | Correo |
|---|---|
| estudiante | `estudiante@campus.test` |
| personal_administrativo | `personal@campus.test` |
| responsable | `responsable@campus.test` |
| administrador | `admin@campus.test` |
