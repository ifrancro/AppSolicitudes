<?php

namespace App\Http\Resources;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Las relaciones se incluyen solo si el controlador las cargó con with():
 * así el recurso nunca dispara consultas N+1 por su cuenta.
 *
 * @mixin Solicitud
 */
class SolicitudResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'ubicacion' => $this->ubicacion,
            'tipo' => new TipoSolicitudResource($this->whenLoaded('tipo')),
            'prioridad' => new PrioridadResource($this->whenLoaded('prioridad')),
            'estado' => new EstadoSolicitudResource($this->whenLoaded('estado')),
            'estudiante' => new PersonaResource($this->whenLoaded('estudiante')),
            'responsable' => $this->whenLoaded('asignacionActiva', fn () => $this->asignacionActiva
                ? new PersonaResource($this->asignacionActiva->responsable)
                : null),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
