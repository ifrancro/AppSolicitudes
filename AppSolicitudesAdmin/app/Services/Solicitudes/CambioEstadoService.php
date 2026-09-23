<?php

namespace App\Services\Solicitudes;

use App\Enums\EstadoNombre;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambio manual de estado (contrato §6.7). No conoce HTTP ni roles: quién puede
 * pedir cada destino lo decide SolicitudPolicy::changeStateTo(). La bitácora
 * la escribe Solicitud::cambiarEstado().
 */
class CambioEstadoService
{
    /** Destinos que admite el cambio manual; pendiente y asignada solo nacen de registrar y asignar. */
    public const DESTINOS_MANUALES = [EstadoNombre::EnProceso, EstadoNombre::Cerrada, EstadoNombre::Cancelada];

    /**
     * @throws ValidationException con errors.estado_id (destino no permitido, igual al actual o transición
     *                             ilegal) o errors.comentario (falta al cancelar).
     */
    public function cambiar(Solicitud $solicitud, EstadoNombre $destino, User $actor, ?string $comentario = null): Solicitud
    {
        $this->validarDestinoManual($destino);

        return DB::transaction(function () use ($solicitud, $destino, $actor, $comentario) {
            // Bloquea la fila: dos cambios simultáneos se evalúan uno tras otro contra el estado real.
            Solicitud::query()->whereKey($solicitud->id)->lockForUpdate()->first();
            $solicitud->refresh()->load('estado');

            $actual = EstadoNombre::from($solicitud->estado->nombre);

            if ($actual === $destino) {
                throw ValidationException::withMessages([
                    'estado_id' => ["La solicitud ya está en estado {$this->etiqueta($destino)}."],
                ]);
            }

            if (! $actual->puedePasarA($destino)) {
                throw ValidationException::withMessages([
                    'estado_id' => ["No se puede pasar una solicitud de {$this->etiqueta($actual)} a {$this->etiqueta($destino)}."],
                ]);
            }

            $comentario = $comentario === null ? null : trim($comentario);
            $comentario = $comentario === '' ? null : $comentario;

            if ($destino === EstadoNombre::Cancelada && $comentario === null) {
                throw ValidationException::withMessages([
                    'comentario' => ['El comentario es obligatorio al cancelar una solicitud.'],
                ]);
            }

            $solicitud->cambiarEstado($destino, $actor, $comentario);
            $solicitud->unsetRelation('estado');

            return $solicitud;
        });
    }

    /**
     * Rechaza pendiente y asignada. Público para que el controlador lo aplique
     * antes de consultar la política y el destino ilegal sea 422, no 403.
     *
     * @throws ValidationException
     */
    public function validarDestinoManual(EstadoNombre $destino): void
    {
        if (! in_array($destino, self::DESTINOS_MANUALES, true)) {
            throw ValidationException::withMessages([
                'estado_id' => ["El estado {$this->etiqueta($destino)} no se puede establecer manualmente."],
            ]);
        }
    }

    private function etiqueta(EstadoNombre $estado): string
    {
        return str_replace('_', ' ', $estado->value);
    }
}
