<?php

// Rutas de la Fase G (notificaciones). Se cargan dentro del grupo autenticado de routes/api.php.

use App\Http\Controllers\Api\V1\NotificacionController;
use Illuminate\Support\Facades\Route;

Route::get('/notificaciones', [NotificacionController::class, 'index']);
Route::patch('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer']);
