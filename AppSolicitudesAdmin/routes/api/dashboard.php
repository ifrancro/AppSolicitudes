<?php

// Rutas de la Fase J (dashboard y reportes). Se cargan dentro del grupo autenticado de routes/api.php.

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReporteController;
use Illuminate\Support\Facades\Route;

// Exclusivo del administrador: el gate ver-reportes se evalúa antes de validar.
Route::middleware('can:ver-reportes')->group(function () {
    Route::get('/dashboard/resumen', [DashboardController::class, 'resumen']);
    Route::get('/reportes/solicitudes', [ReporteController::class, 'solicitudes']);
    Route::get('/reportes/solicitudes-por-tipo', [ReporteController::class, 'solicitudesPorTipo']);
    Route::get('/reportes/tiempos-atencion', [ReporteController::class, 'tiemposAtencion']);
});
