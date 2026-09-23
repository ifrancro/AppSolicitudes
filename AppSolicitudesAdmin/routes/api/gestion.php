<?php

use App\Http\Controllers\Api\V1\AsignacionController;
use App\Http\Controllers\Api\V1\GestionSolicitudController;
use App\Http\Controllers\Api\V1\ResponsableController;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Route;

// Rutas de la Fase H (gestión administrativa). Se cargan dentro del grupo autenticado de routes/api.php.
// Cada ruta autoriza con `can:` (política/gate) antes de validar: 403 antes que 422.

Route::get('/solicitudes', [GestionSolicitudController::class, 'index'])
    ->middleware('can:viewAny,'.Solicitud::class);

Route::patch('/solicitudes/{solicitud}/clasificacion', [GestionSolicitudController::class, 'clasificar'])
    ->middleware('can:classify,solicitud');

Route::get('/usuarios/responsables', [ResponsableController::class, 'index'])
    ->middleware('can:listar-responsables');

Route::get('/solicitudes/{solicitud}/asignaciones', [AsignacionController::class, 'index'])
    ->middleware('can:viewAssignments,solicitud');

Route::post('/solicitudes/{solicitud}/asignaciones', [AsignacionController::class, 'store'])
    ->middleware('can:assign,solicitud');
