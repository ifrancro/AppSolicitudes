<?php

namespace App\Http\Resources;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notificacion
 */
class NotificacionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'mensaje' => $this->mensaje,
            'leido' => $this->leido,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
