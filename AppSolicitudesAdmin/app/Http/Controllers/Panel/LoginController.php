<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('panel.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // `activo` forma parte de las credenciales: una cuenta desactivada no entra.
        $entro = Auth::attempt([
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'activo' => true,
        ]);

        if (! $entro) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas o la cuenta está desactivada.',
            ]);
        }

        // El panel es solo para el personal; los estudiantes usan la app móvil.
        if (Gate::denies('acceder-panel')) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'Este panel es solo para el personal. Los estudiantes usan la app móvil.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel.inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
