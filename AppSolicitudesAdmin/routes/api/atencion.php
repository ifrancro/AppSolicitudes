<?php

use App\Http\Controllers\Api\V1\AccionController;
use App\Http\Controllers\Api\V1\AtencionSolicitudController;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Route;

// Rutas de la Fase I (atención de solicitudes). Se cargan dentro del grupo autenticado de routes/api.php.
// Cada ruta autoriza con `can:` (política) antes de validar: 403 antes que 422.

Route::get('/solicitudes-asignadas', [AtencionSolicitudController::class, 'asignadas'])
    ->middleware('can:viewAssigned,'.Solicitud::class);

Route::patch('/solicitudes/{solicitud}/estado', [AtencionSolicitudController::class, 'cambiarEstado'])
    ->middleware('can:changeState,solicitud');

Route::post('/solicitudes/{solicitud}/acciones', [AccionController::class, 'store'])
    ->middleware('can:createAction,solicitud');

Route::get('/solicitudes/{solicitud}/acciones', [AccionController::class, 'index'])
    ->middleware('can:viewActions,solicitud');
