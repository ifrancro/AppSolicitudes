<?php

namespace App\Services\Solicitudes;

use App\Enums\EstadoNombre;
use App\Enums\RolNombre;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asigna (o reasigna) una solicitud a un responsable. No conoce HTTP: la usan
 * la API y, mas adelante, el panel web.
 */
class AsignacionService
{
    /**
     * @throws ValidationException si la solicitud está cerrada o cancelada o el responsable no es válido.
     */
    public function asignar(Solicitud $solicitud, User $responsable, User $asignadoPor, ?string $comentario = null): Asignacion
    {
        return DB::transaction(function () use ($solicitud, $responsable, $asignadoPor, $comentario) {
            // Bloquea la fila: dos asignaciones simultaneas se serializan en lugar de chocar con el indice unico.
            Solicitud::query()->whereKey($solicitud->id)->lockForUpdate()->first();
            $solicitud->refresh()->load('estado');

            $estado = $solicitud->estado->nombre;

            if (in_array($estado, [EstadoNombre::Cerrada->value, EstadoNombre::Cancelada->value], true)) {
                throw ValidationException::withMessages([
                    'solicitud' => ["No se puede asignar una solicitud {$estado}."],
                ]);
            }

            $this->validarResponsable($solicitud, $responsable);

            Asignacion::query()
                ->where('solicitud_id', $solicitud->id)
                ->where('activo', true)
                ->update(['activo' => false, 'fecha_fin' => now()]);

            $asignacion = Asignacion::create([
                'solicitud_id' => $solicitud->id,
                'responsable_id' => $responsable->id,
                'asignado_por' => $asignadoPor->id,
                'activo' => true,
                'fecha_asignacion' => now(),
            ]);

            if ($estado === EstadoNombre::Pendiente->value) {
                $solicitud->cambiarEstado(EstadoNombre::Asignada, $asignadoPor, $comentario);
            }

            return $asignacion->load(['responsable', 'asignadoPor']);
        });
    }

    private function validarResponsable(Solicitud $solicitud, User $responsable): void
    {
        $responsable->loadMissing('rol');

        if (! $responsable->tieneRol(RolNombre::Responsable)) {
            throw ValidationException::withMessages([
                'responsable_id' => ['El usuario seleccionado no tiene el rol de responsable.'],
            ]);
        }

        if (! $responsable->activo) {
            throw ValidationException::withMessages([
                'responsable_id' => ['El responsable seleccionado está desactivado.'],
            ]);
        }

        $yaEsResponsable = $solicitud->asignaciones()
            ->where('activo', true)
            ->where('responsable_id', $responsable->id)
            ->exists();

        if ($yaEsResponsable) {
            throw ValidationException::withMessages([
                'responsable_id' => ['El usuario seleccionado ya es el responsable de esta solicitud.'],
            ]);
        }
    }
}
