<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AsignarSolicitudRequest;
use App\Http\Resources\AsignacionResource;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\AsignacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AsignacionController extends Controller
{
    public function index(Solicitud $solicitud): AnonymousResourceCollection
    {
        $asignaciones = $solicitud->asignaciones()
            ->with(['responsable', 'asignadoPor'])
            ->orderByDesc('fecha_asignacion')
            ->orderByDesc('id')
            ->get();

        return AsignacionResource::collection($asignaciones);
    }

    public function store(AsignarSolicitudRequest $request, Solicitud $solicitud, AsignacionService $servicio): JsonResponse
    {
        $responsable = User::findOrFail($request->integer('responsable_id'));

        $asignacion = $servicio->asignar($solicitud, $responsable, $request->user(), $request->validated('comentario'));

        return (new AsignacionResource($asignacion))->response()->setStatusCode(201);
    }
}
