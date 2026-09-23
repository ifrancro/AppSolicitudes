# Preparación de la API administrativa

## Estado observado

Al consultar origin el 22 de septiembre de 2026, `origin/develop` continúa en
`591171a` (Creacion de Infraestructura). Las ramas `feat/app/*` contienen avances
de Flutter, pero no migraciones, autenticación ni endpoints Laravel nuevos.
No se han fusionado esas ramas de Flutter en esta rama administrativa.

`GET /api/v1/solicitudes` sigue pendiente. No se registra una ruta simulada ni se
crea otro esquema de usuarios para aparentar que la funcionalidad está lista.

## Avance compartido: diagnóstico de PostgreSQL

Desde `AppSolicitudesAdmin`:

```powershell
php artisan campus:database-check
```

El comando utiliza la conexión Laravel configurada y solo consulta los nombres
de las tablas del esquema actual en el catálogo PostgreSQL. No ejecuta
migraciones, crea tablas, lee datos personales ni muestra credenciales.

- Código 0: las 11 tablas funcionales están presentes y no existe `users` en el
  mismo esquema.
- Código 1: conexión incorrecta, tablas ausentes o presencia de `users` que debe
  revisarse antes de integrar `usuarios`.

La presencia de tablas no demuestra que sus columnas, restricciones, datos
iniciales o autenticación sean correctos. Esta comprobación no autoriza a
ejecutar migraciones. Una base vacía debe producir código 1.

## Dependencias del listado

1. Acordar e incorporar las migraciones del esquema de 11 tablas y los catálogos.
2. Adaptar un único modelo autenticable a `usuarios` y `password_hash`.
3. Incorporar Sanctum y comprobación de usuario activo sin cambiar el contrato.
4. Definir los permisos de `administrador` y `personal_administrativo`.
5. Acordar parámetros de filtros, límites de paginación y formato JSON con B.
6. Preparar una base exclusiva de pruebas antes de usar `RefreshDatabase`.

El cliente Flutter inspeccionado admite varias formas de token, usuario y
catálogos porque aún espera confirmar el contrato. Esa tolerancia no constituye
un contrato definitivo para el listado administrativo.

## Pruebas del diagnóstico

```powershell
php artisan test
```

Las pruebas nuevas simulan la conexión: verifican base vacía, esquema parcial,
tablas técnicas adicionales, identidad duplicada, driver incorrecto y ocultación
de errores sensibles. No usan `RefreshDatabase` ni reconstruyen tablas.

La ejecución real del diagnóstico es una comprobación de integración de solo
lectura. No sustituye las futuras pruebas del endpoint sobre PostgreSQL aislado.

## Ejecución local

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Con los assets ya compilados, abrir http://127.0.0.1:8000. Para recompilarlos:

```powershell
npm.cmd run build
```

No usar `composer run setup` en esta fase: incluye ejecución de migraciones.

## Preparación del Pull Request

Título propuesto: `feat(api): agregar diagnóstico de preparación PostgreSQL`

Destino: `develop`.

Descripción propuesta:

> El listado administrativo depende de un esquema y una autenticación que aún
> no están integrados en develop. Este cambio incorpora un comando de solo
> lectura para comprobar PostgreSQL y detectar tablas funcionales ausentes o
> una tabla users pendiente de integración. Incluye pruebas de resultados y
> errores sin modificar la base de datos. No implementa endpoints ni migra datos.

Validación ejecutada: `php artisan test`, 8 pruebas y 42 aserciones aprobadas
(6 pruebas nuevas y los 2 ejemplos existentes). El diagnóstico real conectó a
PostgreSQL y devolvió código 1 por ausencia de las 11 tablas, el resultado
esperado para la base vacía. No se ha creado un PR remoto.

`package-lock.json` procede de la preparación anterior. Conviene revisarlo e
integrarlo mediante un PR independiente hacia `develop`; no forma parte del
diagnóstico. `.env`, dependencias y assets generados deben permanecer ignorados.
Todo commit, push y apertura de PR requiere aprobación del usuario.
