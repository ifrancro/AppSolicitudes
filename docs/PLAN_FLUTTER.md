# Plan de desarrollo — App móvil Flutter (Campus Connect)

Aplicación móvil orientada al estudiante. Consume la API REST compartida
(`http://10.0.2.2:8000/api/v1` desde el emulador Android, `http://127.0.0.1:8000/api/v1`
en escritorio o web). El backend Laravel y sus migraciones los desarrolla el
Desarrollador A; este plan cubre únicamente el cliente Flutter.

## 1. Alcance

12 endpoints móviles, agrupados en cinco bloques:

| Historia | Endpoints |
|---|---|
| Autenticación | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| HU-01 Registrar solicitud | `POST /solicitudes`, `GET /mis-solicitudes`, `GET /tipos-solicitud` |
| HU-02 Evidencias | `POST /solicitudes/{id}/adjuntos`, `GET /solicitudes/{id}/adjuntos`, `GET /adjuntos/{id}/archivo` |
| HU-03 Seguimiento | `GET /solicitudes/{id}`, `GET /solicitudes/{id}/historial-estados` |
| HU-04 Notificaciones | `GET /notificaciones`, `PATCH /notificaciones/{id}/leer` |

Fuera de alcance en la app móvil: clasificación, asignaciones, registro de acciones,
dashboard y reportes. Esos endpoints los consume la aplicación web con Blade.

## 2. Flujo de ramas

Se respeta el modelo ya definido en `docs/BRANCHING.md`.

```
feature/flutter-<fase>  ->  develop  ->  test  ->  main
```

- Una rama `feature/` por fase. Merge a `develop` cuando la fase compila y pasa
  `flutter analyze` y `flutter test`.
- `develop -> test` al cerrar cada bloque funcional: fases 0–3, luego 4–6, luego 7–9.
  En `test` se valida contra el backend real levantado con Docker.
- `test -> main` solo con entregables verificados.
- Commits en español, formato Conventional Commits: `feat(movil): registrar solicitud`.

## 3. Arquitectura del proyecto

Organización por funcionalidad con capas internas, suficiente para el tamaño del
proyecto y fácil de sustentar.

```
appsolicitudes/lib/
  main.dart
  app.dart                      # MaterialApp.router + tema
  core/
    config/env.dart             # baseUrl por --dart-define
    network/api_client.dart     # Dio + interceptores
    network/api_exception.dart  # mapeo de errores HTTP a mensajes en español
    storage/token_storage.dart  # flutter_secure_storage
    router/app_router.dart      # go_router + guard de sesión
    theme/app_theme.dart        # Material 3, color semilla, tipografía
    widgets/                    # estado vacío, error, carga, chips de estado
  features/
    auth/           data/ domain/ presentation/
    solicitudes/    data/ domain/ presentation/
    adjuntos/       data/ domain/ presentation/
    notificaciones/ data/ domain/ presentation/
    catalogos/      data/ domain/ presentation/
```

Convención por capa:

- `domain/`: modelos inmutables (`Usuario`, `Solicitud`, `TipoSolicitud`,
  `HistorialEstado`, `Adjunto`, `Notificacion`) con `fromJson`.
- `data/`: repositorios que hablan con `ApiClient` y devuelven modelos de dominio.
- `presentation/`: providers de Riverpod, pantallas y widgets.

## 4. Dependencias

```yaml
dependencies:
  flutter_riverpod: ^2.6.1       # estado
  dio: ^5.7.0                    # HTTP y multipart
  go_router: ^14.6.2             # navegación declarativa con guard
  flutter_secure_storage: ^9.2.4 # token
  image_picker: ^1.1.2           # cámara y galería (HU-02)
  file_picker: ^8.1.6            # documentos (HU-02)
  intl: ^0.19.0                  # fechas en español
  cached_network_image: ^3.4.1   # miniaturas de evidencias
  shimmer: ^3.0.0                # esqueletos de carga
dev_dependencies:
  mocktail: ^1.0.4
```

## 5. Fases

### Fase 0 — Base del proyecto

Rama `feature/flutter-base`.

- Añadir dependencias y eliminar el contador de ejemplo de `main.dart`.
- Crear la estructura de carpetas de la sección 3.
- `Env.baseUrl` leído con `String.fromEnvironment` sobre la clave `API_BASE_URL`,
  con valor por defecto `http://10.0.2.2:8000/api/v1`.
- Tema Material 3: color semilla institucional, modo claro y oscuro,
  `CardTheme` e `InputDecorationTheme` consistentes.
- Permiso `INTERNET` en Android y tráfico HTTP sin cifrar habilitado solo en
  depuración, para alcanzar el backend local.

Criterio de cierre: la app arranca con una pantalla vacía y el tema aplicado.

### Fase 1 — Núcleo de red

Rama `feature/flutter-core-red`.

- `ApiClient` sobre Dio: URL base, cabecera `Accept: application/json`,
  tiempos de espera de 15 segundos.
- Interceptor de petición: adjunta la cabecera de autorización cuando hay token.
- Interceptor de respuesta: ante un 401 limpia el token y redirige a login.
- `ApiException` traduce los códigos 422 (errores de validación por campo),
  403, 404 y 500 a mensajes legibles en español.
- `TokenStorage` con `flutter_secure_storage`.

Criterio de cierre: prueba unitaria del mapeo de errores con `mocktail`.

### Fase 2 — Autenticación

Rama `feature/flutter-auth`.

- `POST /auth/login` con correo y contraseña; guarda el token y el usuario.
- `GET /auth/me` al iniciar la app para restaurar la sesión.
- `POST /auth/logout` con limpieza local aunque la petición falle.
- `authProvider` expone tres estados: cargando, autenticado y no autenticado.
- `go_router` con redirección: sin sesión, toda ruta lleva a `/login`.
- Pantalla de login: identidad visual, campos con validación en vivo,
  alternar visibilidad de la contraseña, botón con indicador de progreso
  y error en `SnackBar`.

Criterio de cierre: iniciar y cerrar sesión contra el backend en Docker.

### Fase 3 — Navegación y catálogos

Rama `feature/flutter-shell`.

- Contenedor con `NavigationBar` de tres destinos: Solicitudes, Notificaciones y Perfil.
- `FloatingActionButton` extendido para "Nueva solicitud".
- `GET /tipos-solicitud` cacheado en memoria mediante un provider.
- Pantalla de perfil: nombre, correo, rol y botón de cerrar sesión.

Criterio de cierre: navegación entre pestañas conservando el estado de cada una.

### Fase 4 — HU-01 Registro y listado de solicitudes

Rama `feature/flutter-solicitudes`.

- `GET /mis-solicitudes` con carga por desplazamiento y `RefreshIndicator`.
- Tarjeta de solicitud: título, chip de estado con color, prioridad, tipo,
  fecha relativa y ubicación.
- Filtro por estado con chips horizontales.
- Formulario de nueva solicitud: título, descripción, tipo tomado del catálogo
  y ubicación opcional. Validación local antes de enviar y mapeo de los errores
  422 al campo correspondiente.
- `POST /solicitudes` y navegación al detalle creado con la respuesta.

Criterio de cierre: crear una solicitud y verla en el listado.

### Fase 5 — HU-03 Detalle y seguimiento

Rama `feature/flutter-seguimiento`.

- `GET /solicitudes/{id}`: encabezado con estado, prioridad, tipo y fechas.
- `GET /solicitudes/{id}/historial-estados`: línea de tiempo vertical con punto,
  conector, nombre del estado, autor del cambio, comentario y fecha.
- Las dos peticiones se lanzan en paralelo con `Future.wait`.
- Esqueleto de carga con `shimmer` en lugar de un indicador centrado.

Criterio de cierre: la línea de tiempo refleja los cambios hechos desde la web.

### Fase 6 — HU-02 Evidencias

Rama `feature/flutter-adjuntos`.

- Hoja inferior con tres opciones: cámara, galería y documento.
- `POST /solicitudes/{id}/adjuntos` como `multipart/form-data`, con barra de
  progreso a partir del avance de envío que reporta Dio.
- Validación previa de tamaño máximo y extensiones permitidas, acordadas con el
  backend antes de implementar.
- `GET /solicitudes/{id}/adjuntos`: cuadrícula de miniaturas; los documentos se
  muestran con icono según la extensión.
- Visor a pantalla completa con `GET /adjuntos/{id}/archivo`, enviando la
  cabecera de autorización.

Criterio de cierre: subir una foto desde el emulador y volver a abrirla.

### Fase 7 — HU-04 Notificaciones

Rama `feature/flutter-notificaciones`.

- `GET /notificaciones` con separación visual entre leídas y no leídas.
- `PATCH /notificaciones/{id}/leer` al tocar, con actualización optimista.
- Insignia con el número de no leídas sobre el icono de la barra de navegación.
- Refresco al abrir la pestaña y al volver la app a primer plano con
  `WidgetsBindingObserver`. No se implementa notificación push en esta entrega.
- Tocar una notificación que tenga solicitud asociada navega a su detalle.

Criterio de cierre: cambiar un estado desde la web y ver la notificación en la app.

### Fase 8 — Pulido de experiencia de usuario

Rama `feature/flutter-ux`.

- Estados vacíos con icono, título y acción sugerida.
- Estados de error con causa y botón de reintentar.
- Esqueletos de carga en todos los listados.
- Mensajes de éxito con `SnackBar` flotante.
- Accesibilidad: objetivos táctiles de 48 dp, contraste suficiente en los chips
  de estado y descripciones semánticas en iconos sin texto.
- Revisión de textos: todo en español, fechas con `intl` y configuración regional `es`.

Criterio de cierre: recorrido completo sin pantallas en blanco ni errores sin mensaje.

### Fase 9 — Pruebas y entrega

Rama `feature/flutter-pruebas`.

- Pruebas unitarias de repositorios con Dio simulado.
- Pruebas de widget del formulario de solicitud y de la tarjeta de listado.
- Una prueba de integración del recorrido completo: iniciar sesión, crear
  solicitud y ver el detalle.
- `flutter analyze` sin advertencias.
- Compilación de APK en modo release definiendo `API_BASE_URL`.
- Actualizar `README.md` con las instrucciones de ejecución.

## 6. Lenguaje visual

- Material 3 con un único color semilla; el resto del esquema se deriva de él.
- Los estados se distinguen por color y texto, nunca solo por color:
  pendiente en gris, asignada en azul, en proceso en ámbar, cerrada en verde
  y cancelada en rojo.
- La prioridad se representa como borde lateral de la tarjeta, no como fondo.
- Una sola acción principal por pantalla.
- Animaciones cortas: transición de página, transición de la tarjeta al detalle
  y cambio suave entre el estado de carga y el contenido.

## 7. Dependencias externas del plan

Antes de la fase 2 se necesita del Desarrollador A:

1. Formato exacto de la respuesta de `POST /auth/login`, incluido el nombre del
   campo que contiene el token.
2. Forma de los objetos `solicitud`, `adjunto`, `notificacion` e
   `historial_estado`, con nombres de campo definitivos, y si las relaciones
   llegan anidadas o por identificador.
3. Estructura de la respuesta paginada de `GET /mis-solicitudes`.
4. Límites de tamaño y tipos aceptados en `POST /solicitudes/{id}/adjuntos`.

Las fases 0, 1 y 3 avanzan sin bloqueo mientras esos contratos se definen.
