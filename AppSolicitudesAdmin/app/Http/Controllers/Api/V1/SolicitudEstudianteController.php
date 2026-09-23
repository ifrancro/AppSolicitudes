<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MisSolicitudesRequest;
use App\Http\Requests\Api\StoreSolicitudRequest;
use App\Http\Resources\HistorialEstadoResource;
use App\Http\Resources\SolicitudResource;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Solicitudes desde el lado del estudiante: alta, listado propio, detalle e historial.
 */
class SolicitudEstudianteController extends Controller
{
    /** Relaciones que necesita SolicitudResource para no disparar N+1. */
    private const RELACIONES = ['tipo', 'prioridad', 'estado', 'asignacionActiva.responsable'];

    public function store(StoreSolicitudRequest $request): JsonResponse
    {
        // Toda solicitud nace pendiente y con prioridad media; el personal la reclasifica.
        // El historial inicial lo escribe SolicitudObserver::created().
        $solicitud = Solicitud::create([
            ...$request->validated(),
            'estudiante_id' => $request->user()->id,
            'estado_id' => EstadoSolicitud::idDe(EstadoNombre::Pendiente),
            'prioridad_id' => Prioridad::where('nombre', PrioridadNombre::Media->value)->firstOrFail()->id,
        ]);

        return (new SolicitudResource($solicitud->load(self::RELACIONES)))
            ->response()
            ->setStatusCode(201);
    }

    public function index(MisSolicitudesRequest $request): AnonymousResourceCollection
    {
        $solicitudes = Solicitud::query()
            ->with(self::RELACIONES)
            // El estudiante siempre queda fijado al usuario autenticado, venga lo que venga en la query.
            ->filtrar([...$request->filtros(), 'estudiante_id' => $request->user()->id])
            ->paginate($request->perPage())
            ->withQueryString();

        return SolicitudResource::collection($solicitudes);
    }

    public function show(Solicitud $solicitud): SolicitudResource
    {
        $this->authorize('view', $solicitud);

        return new SolicitudResource($solicitud->load([...self::RELACIONES, 'estudiante']));
    }

    public function historialEstados(Solicitud $solicitud): AnonymousResourceCollection
    {
        $this->authorize('view', $solicitud);

        return HistorialEstadoResource::collection(
            $solicitud->historialEstados()
                ->with(['estado', 'autor'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
        );
    }
}
