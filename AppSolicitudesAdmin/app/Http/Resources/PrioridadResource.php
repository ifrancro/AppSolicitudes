<?php

namespace App\Http\Resources;

use App\Models\Prioridad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Prioridad
 */
class PrioridadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nivel' => $this->nivel,
        ];
    }
}
