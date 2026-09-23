<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Forma de SolicitudResource más `cerrada_at`, que el controlador calcula con
 * una subconsulta (atributo `cerrada_at`) para no consultar por fila.
 */
class ReporteSolicitudResource extends SolicitudResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cerradaAt = $this->resource->getAttribute('cerrada_at');

        return parent::toArray($request) + [
            'cerrada_at' => $cerradaAt ? Carbon::parse($cerradaAt, 'UTC')->utc()->toIso8601String() : null,
        ];
    }
}
