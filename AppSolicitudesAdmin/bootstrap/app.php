<?php

use App\Http\Middleware\EnsureUsuarioActivo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'usuario.activo' => EnsureUsuarioActivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Cuerpo de error único para la API: {"message": "..."} (y "errors" en 422,
        // que Laravel ya genera). Los mensajes por defecto están en inglés y el de
        // 404 filtra el nombre del modelo, por eso se sustituyen.
        $esApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $esApi($request)
            ? response()->json(['message' => 'No autenticado.'], 401) : null);

        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e, Request $request) => $esApi($request)
            ? response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403) : null);

        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $request) => $esApi($request)
            ? response()->json(['message' => 'Recurso no encontrado.'], 404) : null);

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $esApi($request)
            ? response()->json(['message' => 'Método no permitido.'], 405) : null);

        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $request) => $esApi($request)
            ? response()->json(['message' => 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'], 429, $e->getHeaders()) : null);
    })->create();
