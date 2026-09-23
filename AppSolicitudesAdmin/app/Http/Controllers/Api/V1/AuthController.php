<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = User::with('rol')->where('email', $request->validated('email'))->first();

        // Mismo mensaje para email inexistente y contraseña errónea: no revela qué cuentas existen.
        if (! $usuario || ! Hash::check($request->validated('password'), $usuario->getAuthPassword())) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'Tu cuenta está desactivada.'], 403);
        }

        $token = $usuario->createToken($request->validated('device_name') ?? 'movil')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => (new UsuarioResource($usuario))->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load('rol'));
    }
}
