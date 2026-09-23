<?php

// Rutas de la Fase F (adjuntos). Se cargan dentro del grupo autenticado de routes/api.php.

use App\Http\Controllers\Api\V1\AdjuntoController;
use Illuminate\Support\Facades\Route;

Route::get('/solicitudes/{solicitud}/adjuntos', [AdjuntoController::class, 'index']);
Route::post('/solicitudes/{solicitud}/adjuntos', [AdjuntoController::class, 'store']);
Route::get('/adjuntos/{adjunto}/archivo', [AdjuntoController::class, 'archivo'])->name('adjuntos.archivo');
