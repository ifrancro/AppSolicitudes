<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegistrarAccionRequest;
use App\Http\Resources\AccionResource;
use App\Models\Solicitud;
use App\Services\Solicitudes\AccionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccionController extends Controller
{
    public function index(Solicitud $solicitud): AnonymousResourceCollection
    {
        $acciones = $solicitud->acciones()
            ->with('responsable')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return AccionResource::collection($acciones);
    }

    public function store(RegistrarAccionRequest $request, Solicitud $solicitud, AccionService $servicio): JsonResponse
    {
        $accion = $servicio->registrar($solicitud, $request->user(), $request->validated('descripcion'));

        return (new AccionResource($accion))->response()->setStatusCode(201);
    }
}
