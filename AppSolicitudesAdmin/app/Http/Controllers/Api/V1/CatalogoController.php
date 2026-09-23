<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoSolicitudResource;
use App\Http\Resources\PrioridadResource;
use App\Http\Resources\TipoSolicitudResource;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\TipoSolicitud;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogos de solo lectura (contrato §4.5 a §4.7). Son públicos para todo
 * usuario autenticado, así que no necesitan política.
 */
class CatalogoController extends Controller
{
    public function tipos(): AnonymousResourceCollection
    {
        return TipoSolicitudResource::collection(TipoSolicitud::query()->orderBy('id')->get());
    }

    public function prioridades(): AnonymousResourceCollection
    {
        return PrioridadResource::collection(Prioridad::query()->orderBy('nivel')->get());
    }

    public function estados(): AnonymousResourceCollection
    {
        return EstadoSolicitudResource::collection(EstadoSolicitud::query()->orderBy('id')->get());
    }
}
