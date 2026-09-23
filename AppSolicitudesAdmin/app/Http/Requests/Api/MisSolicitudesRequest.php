<?php

namespace App\Http\Requests\Api;

use App\Models\Solicitud;

/**
 * Listado propio del estudiante: mismos parámetros que el resto de listados,
 * pero solo acepta los filtros de contrato §5.2 y solo para el rol estudiante.
 */
class MisSolicitudesRequest extends ListadoSolicitudesRequest
{
    /** Registrar solicitudes y verlas como propias son capacidades del mismo rol (SolicitudPolicy::create). */
    public function authorize(): bool
    {
        return $this->user()->can('create', Solicitud::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return array_intersect_key(
            parent::filtros(),
            array_flip(['estado_id', 'tipo_id', 'q', 'desde', 'hasta', 'orden']),
        );
    }
}
