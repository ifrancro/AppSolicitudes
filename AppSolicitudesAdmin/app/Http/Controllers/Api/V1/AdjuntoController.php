<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubirAdjuntoRequest;
use App\Http\Resources\AdjuntoResource;
use App\Models\Adjunto;
use App\Models\Solicitud;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjuntoController extends Controller
{
    /** Disco privado: los archivos nunca se sirven desde una URL pública. */
    private const DISCO = 'local';

    public function index(Solicitud $solicitud): JsonResponse
    {
        $this->authorize('view', $solicitud);

        $adjuntos = $solicitud->adjuntos()->with('autor')->orderBy('created_at')->orderBy('id')->get();

        return AdjuntoResource::collection($adjuntos)->response();
    }

    public function store(SubirAdjuntoRequest $request, Solicitud $solicitud): JsonResponse
    {
        $archivo = $request->file('archivo');

        // Mime y extensión salen del contenido real; el nombre enviado se descarta.
        $mime = $archivo->getMimeType();
        $ruta = Storage::disk(self::DISCO)->putFileAs(
            'adjuntos/'.$solicitud->id,
            $archivo,
            (string) Str::uuid().'.'.Adjunto::EXTENSIONES[$mime],
        );

        $adjunto = $solicitud->adjuntos()->create([
            'url_archivo' => $ruta,
            'tipo_archivo' => $mime,
            'subido_por' => $request->user()->id,
        ]);

        return (new AdjuntoResource($adjunto->load('autor')))->response()->setStatusCode(201);
    }

    public function archivo(Adjunto $adjunto): StreamedResponse
    {
        $this->authorize('view', $adjunto);

        $disco = Storage::disk(self::DISCO);
        abort_unless($disco->exists($adjunto->url_archivo), 404);

        return $disco->response($adjunto->url_archivo, basename($adjunto->url_archivo), [
            'Content-Type' => $adjunto->tipo_archivo,
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
