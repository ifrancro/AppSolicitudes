<?php

use Illuminate\Support\Facades\Route;

// El prefijo /api/v1 se declara en bootstrap/app.php.
Route::get('/ping', fn () => response()->json([
    'status' => 'ok',
    'app' => config('app.name'),
    'timestamp' => now()->toIso8601String(),
]));
