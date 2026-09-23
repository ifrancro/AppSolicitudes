<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FiltroReporteRequest;
use App\Http\Requests\Api\ListadoSolicitudesRequest;
use App\Http\Resources\ReporteSolicitudResource;
use App\Models\Solicitud;
use App\Services\Reportes\ReportesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReporteController extends Controller
{
    public function solicitudes(ListadoSolicitudesRequest $request, ReportesService $reportes): AnonymousResourceCollection
    {
        // El reporte solo admite los filtros del contrato §6.11.
        $filtros = array_intersect_key(
            $request->filtros(),
            array_flip(['desde', 'hasta', 'estado_id', 'tipo_id', 'prioridad_id', 'orden']),
        );

        $solicitudes = Solicitud::query()
            ->select('solicitudes.*')
            ->selectSub($reportes->subconsultaCerradaAt(), 'cerrada_at')
            ->with(['tipo', 'prioridad', 'estado', 'estudiante', 'asignacionActiva.responsable'])
            ->filtrar($filtros)
            ->paginate($request->perPage());

        return ReporteSolicitudResource::collection($solicitudes);
    }

    public function solicitudesPorTipo(FiltroReporteRequest $request, ReportesService $reportes): JsonResponse
    {
        return response()->json(['data' => $reportes->solicitudesPorTipo($request->filtros())]);
    }

    public function tiemposAtencion(FiltroReporteRequest $request, ReportesService $reportes): JsonResponse
    {
        return response()->json(['data' => $reportes->tiemposAtencion($request->filtros())]);
    }
}
