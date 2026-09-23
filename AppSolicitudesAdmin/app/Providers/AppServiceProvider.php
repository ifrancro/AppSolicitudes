<?php

namespace App\Providers;

use App\Enums\RolNombre;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 5 intentos por minuto por correo e IP: frena la fuerza bruta sobre el login.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));

        // Habilidades que no dependen de un modelo concreto.
        Gate::define('ver-reportes', fn (User $user) => $user->tieneRol(RolNombre::Administrador));
        Gate::define('listar-responsables', fn (User $user) => $user->tieneRol(
            RolNombre::PersonalAdministrativo, RolNombre::Administrador
        ));
    }
}
