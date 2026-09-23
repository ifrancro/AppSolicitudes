<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoNombre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CambiarEstadoRequest;
use App\Http\Requests\Api\ListadoSolicitudesRequest;
use App\Http\Resources\SolicitudResource;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Services\Solicitudes\CambioEstadoService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

/**
 * Bandeja del responsable y cambio de estado (contrato §6.6 y §6.7). Cada ruta
 * autoriza con `can:` antes de validar; el destino concreto se autoriza aquí.
 */
class AtencionSolicitudController extends Controller
{
    private const RELACIONES = ['tipo', 'prioridad', 'estado', 'estudiante', 'asignacionActiva.responsable'];

    /** Filtros que admite este listado; el resto se ignora. */
    private const FILTROS = ['estado_id', 'prioridad_id', 'q', 'orden'];

    public function asignadas(ListadoSolicitudesRequest $request): AnonymousResourceCollection
    {
        $solicitudes = Solicitud::query()
            ->whereHas('asignaciones', fn ($a) => $a
                ->where('activo', true)
                ->where('responsable_id', $request->user()->id))
            ->with(self::RELACIONES)
            ->filtrar(Arr::only($request->filtros(), self::FILTROS))
            ->paginate($request->perPage());

        return SolicitudResource::collection($solicitudes);
    }

    public function cambiarEstado(CambiarEstadoRequest $request, Solicitud $solicitud, CambioEstadoService $servicio): SolicitudResource
    {
        $destino = EstadoNombre::from(EstadoSolicitud::findOrFail($request->integer('estado_id'))->nombre);

        // Primero el 422 por destino no permitido; después el 403 por rol (cancelar).
        $servicio->validarDestinoManual($destino);
        $this->authorize('changeStateTo', [$solicitud, $destino]);

        $servicio->cambiar($solicitud, $destino, $request->user(), $request->validated('comentario'));

        return new SolicitudResource($solicitud->load(self::RELACIONES));
    }
}
