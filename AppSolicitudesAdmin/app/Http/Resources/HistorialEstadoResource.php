<?php

namespace App\Http\Resources;

use App\Models\HistorialEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HistorialEstado
 */
class HistorialEstadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => new EstadoSolicitudResource($this->whenLoaded('estado')),
            'comentario' => $this->comentario,
            'autor' => new PersonaResource($this->whenLoaded('autor')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
