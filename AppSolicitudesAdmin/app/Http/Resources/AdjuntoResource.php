<?php

namespace App\Http\Resources;

use App\Models\Adjunto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nunca expone la ruta interna del disco: `nombre` es solo el basename y
 * `url` apunta a la descarga autorizada.
 *
 * @mixin Adjunto
 */
class AdjuntoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'nombre' => basename($this->url_archivo),
            'tipo_archivo' => $this->tipo_archivo,
            'subido_por' => new PersonaResource($this->whenLoaded('autor')),
            'url' => route('adjuntos.archivo', $this->id),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
