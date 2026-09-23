<?php

namespace App\Services\Solicitudes;

use App\Enums\EstadoNombre;
use App\Models\Accion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registra las acciones que el responsable realiza sobre una solicitud.
 * Quién puede hacerlo lo decide SolicitudPolicy::createAction().
 */
class AccionService
{
    /** Estados en los que la solicitud admite acciones. */
    public const ESTADOS_ADMITIDOS = [EstadoNombre::Asignada, EstadoNombre::EnProceso];

    /**
     * @throws ValidationException si la solicitud no está asignada ni en proceso.
     */
    public function registrar(Solicitud $solicitud, User $responsable, string $descripcion): Accion
    {
        $solicitud->loadMissing('estado');
        $estado = EstadoNombre::from($solicitud->estado->nombre);

        if (! in_array($estado, self::ESTADOS_ADMITIDOS, true)) {
            throw ValidationException::withMessages([
                'solicitud' => ['Solo se pueden registrar acciones en solicitudes asignadas o en proceso.'],
            ]);
        }

        return Accion::create([
            'solicitud_id' => $solicitud->id,
            'responsable_id' => $responsable->id,
            'descripcion' => $descripcion,
        ])->load('responsable');
    }
}
