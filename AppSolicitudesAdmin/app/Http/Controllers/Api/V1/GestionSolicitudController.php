<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClasificarSolicitudRequest;
use App\Http\Requests\Api\ListadoSolicitudesRequest;
use App\Http\Resources\SolicitudResource;
use App\Models\Solicitud;
use App\Services\Solicitudes\ClasificacionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Listado global y clasificacion (contrato 6.1 y 6.2). La autorizacion va en
 * el middleware `can:` de las rutas para que un 403 gane sobre un 422.
 */
class GestionSolicitudController extends Controller
{
    public const RELACIONES = ['tipo', 'prioridad', 'estado', 'estudiante', 'asignacionActiva.responsable'];

    public function index(ListadoSolicitudesRequest $request): AnonymousResourceCollection
    {
        $solicitudes = Solicitud::query()
            ->with(self::RELACIONES)
            ->filtrar($request->filtros())
            ->paginate($request->perPage());

        return SolicitudResource::collection($solicitudes);
    }

    public function clasificar(ClasificarSolicitudRequest $request, Solicitud $solicitud, ClasificacionService $servicio): SolicitudResource
    {
        $servicio->clasificar($solicitud, $request->integer('tipo_id') ?: null, $request->integer('prioridad_id') ?: null);

        return new SolicitudResource($solicitud->load(self::RELACIONES));
    }
}
