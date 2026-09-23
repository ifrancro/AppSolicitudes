<?php

use App\Http\Controllers\Api\V1\CatalogoController;
use Illuminate\Support\Facades\Route;

// Rutas de la Fase D (catálogos). Se cargan dentro del grupo autenticado de routes/api.php.
Route::get('/tipos-solicitud', [CatalogoController::class, 'tipos']);
Route::get('/prioridades', [CatalogoController::class, 'prioridades']);
Route::get('/estados-solicitud', [CatalogoController::class, 'estados']);
