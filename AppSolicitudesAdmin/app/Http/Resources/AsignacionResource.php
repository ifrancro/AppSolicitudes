<?php

namespace App\Http\Resources;

use App\Models\Asignacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asignacion
 */
class AsignacionResource extends JsonResource
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
            'asignado_por' => new PersonaResource($this->whenLoaded('asignadoPor')),
            'activo' => $this->activo,
            'fecha_asignacion' => $this->fecha_asignacion->toIso8601String(),
            'fecha_fin' => $this->fecha_fin?->toIso8601String(),
        ];
    }
}
