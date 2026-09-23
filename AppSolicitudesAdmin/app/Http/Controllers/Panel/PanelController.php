<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /**
     * Cada rol aterriza en su vista de trabajo.
     */
    public function inicio(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario->esResponsable()) {
            return redirect()->route('panel.solicitudes.asignadas');
        }

        return redirect()->route('panel.solicitudes.index');
    }
}
