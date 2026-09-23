<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// El prefijo /api/v1 se declara en bootstrap/app.php.
Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'timestamp' => now()->toIso8601String(),
]));

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'usuario.activo'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Un archivo por fase: evita conflictos de fusión entre ramas paralelas.
    require __DIR__.'/api/catalogos.php';
    require __DIR__.'/api/solicitudes.php';
    require __DIR__.'/api/adjuntos.php';
    require __DIR__.'/api/notificaciones.php';
    require __DIR__.'/api/gestion.php';
    require __DIR__.'/api/atencion.php';
    require __DIR__.'/api/dashboard.php';
});
