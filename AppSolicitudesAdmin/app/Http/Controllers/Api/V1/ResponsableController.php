<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RolNombre;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResponsableResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResponsableController extends Controller
{
    /** GET /usuarios/responsables: una sola consulta agregada, sin N+1. */
    public function index(): AnonymousResourceCollection
    {
        $responsables = User::query()
            ->whereHas('rol', fn ($q) => $q->where('nombre', RolNombre::Responsable->value))
            ->where('activo', true)
            ->withCount(['asignaciones as asignaciones_activas' => fn ($q) => $q->where('activo', true)])
            ->orderBy('asignaciones_activas')
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return ResponsableResource::collection($responsables);
    }
}
