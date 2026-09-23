<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FiltroReporteRequest;
use App\Services\Reportes\ReportesService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardPanelController extends Controller
{
    /** Dashboard y reportes (HU-10): solo administrador, igual que en la API. */
    public function index(FiltroReporteRequest $request, ReportesService $reportes): View
    {
        Gate::authorize('ver-reportes');

        $filtros = $request->filtros();

        return view('panel.dashboard', [
            'resumen' => $reportes->resumen(),
            'porTipo' => $reportes->solicitudesPorTipo($filtros),
            'tiempos' => $reportes->tiemposAtencion($filtros),
            'filtros' => $filtros,
        ]);
    }
}
