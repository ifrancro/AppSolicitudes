<?php

namespace App\Http\Resources;

use App\Models\Accion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Accion
 */
class AccionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'responsable' => new PersonaResource($this->whenLoaded('responsable')),
            'descripcion' => $this->descripcion,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
