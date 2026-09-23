<?php

namespace App\Services\Solicitudes;

use App\Enums\EstadoNombre;
use App\Models\Solicitud;
use Illuminate\Validation\ValidationException;

/**
 * Cambia el tipo y/o la prioridad de una solicitud. Independiente de HTTP.
 */
class ClasificacionService
{
    /**
     * @throws ValidationException si no se indica nada o la solicitud está cerrada o cancelada.
     */
    public function clasificar(Solicitud $solicitud, ?int $tipoId, ?int $prioridadId): Solicitud
    {
        if ($tipoId === null && $prioridadId === null) {
            throw ValidationException::withMessages([
                'tipo_id' => ['Debes indicar al menos el tipo o la prioridad.'],
                'prioridad_id' => ['Debes indicar al menos el tipo o la prioridad.'],
            ]);
        }

        $solicitud->loadMissing('estado');
        $estado = $solicitud->estado->nombre;

        if (in_array($estado, [EstadoNombre::Cerrada->value, EstadoNombre::Cancelada->value], true)) {
            throw ValidationException::withMessages([
                'solicitud' => ["No se puede clasificar una solicitud {$estado}."],
            ]);
        }

        $solicitud->fill(array_filter([
            'tipo_id' => $tipoId,
            'prioridad_id' => $prioridadId,
        ], fn ($valor) => $valor !== null))->save();

        return $solicitud;
    }
}
