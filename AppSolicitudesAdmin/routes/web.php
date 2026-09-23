<?php

use App\Http\Controllers\Panel\DashboardPanelController;
use App\Http\Controllers\Panel\LoginController;
use App\Http\Controllers\Panel\PanelController;
use App\Http\Controllers\Panel\SolicitudAccionPanelController;
use App\Http\Controllers\Panel\SolicitudPanelController;
use Illuminate\Support\Facades\Route;

// Panel web (Blade). Autenticación por sesión y servicios compartidos con la API:
// no consume la API por HTTP (ver docs/PANEL_WEB.md).
Route::redirect('/', '/panel');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:acceder-panel'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [PanelController::class, 'inicio'])->name('inicio');

    Route::get('/solicitudes', [SolicitudPanelController::class, 'index'])->name('solicitudes.index');
    Route::get('/asignadas', [SolicitudPanelController::class, 'asignadas'])->name('solicitudes.asignadas');
    Route::get('/solicitudes/{solicitud}', [SolicitudPanelController::class, 'show'])->name('solicitudes.show');

    Route::post('/solicitudes/{solicitud}/clasificacion', [SolicitudAccionPanelController::class, 'clasificar'])->name('solicitudes.clasificar');
    Route::post('/solicitudes/{solicitud}/asignacion', [SolicitudAccionPanelController::class, 'asignar'])->name('solicitudes.asignar');
    Route::post('/solicitudes/{solicitud}/estado', [SolicitudAccionPanelController::class, 'cambiarEstado'])->name('solicitudes.estado');
    Route::post('/solicitudes/{solicitud}/acciones', [SolicitudAccionPanelController::class, 'registrarAccion'])->name('solicitudes.acciones');
    Route::get('/adjuntos/{adjunto}', [SolicitudAccionPanelController::class, 'adjunto'])->name('adjuntos.descargar');

    Route::get('/dashboard', [DashboardPanelController::class, 'index'])->name('dashboard');
});
