<?php

use App\Http\Controllers\Api\V1\SolicitudEstudianteController;
use Illuminate\Support\Facades\Route;

// Rutas de la Fase E (solicitudes del estudiante). Se cargan dentro del grupo autenticado de routes/api.php.
// GET /solicitudes (listado global) pertenece a otra fase y no se registra aquí.
Route::post('/solicitudes', [SolicitudEstudianteController::class, 'store']);
Route::get('/mis-solicitudes', [SolicitudEstudianteController::class, 'index']);
Route::get('/solicitudes/{solicitud}', [SolicitudEstudianteController::class, 'show'])->whereNumber('solicitud');
Route::get('/solicitudes/{solicitud}/historial-estados', [SolicitudEstudianteController::class, 'historialEstados'])->whereNumber('solicitud');
