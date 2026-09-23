<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Responsable con su carga actual. `asignaciones_activas` llega calculado
 * por la consulta del listado (withCount), no se cuenta por fila.
 *
 * @mixin User
 */
class ResponsableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'asignaciones_activas' => (int) $this->asignaciones_activas,
        ];
    }
}
