<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario desactivado después de iniciar sesión pierde el acceso en su
 * siguiente petición: se revoca el token y se responde 401, que el cliente
 * móvil interpreta como sesión inválida.
 */
class EnsureUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            $usuario->tokens()->delete();

            return response()->json(['message' => 'Tu cuenta está desactivada.'], 401);
        }

        return $next($request);
    }
}
