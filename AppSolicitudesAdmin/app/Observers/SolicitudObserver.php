<?php

namespace App\Observers;

use App\Models\HistorialEstado;
use App\Models\Notificacion;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Garantiza que ningún camino cambie el estado sin dejar rastro en
 * `historial_estados`.
 */
class SolicitudObserver
{
    public function created(Solicitud $solicitud): void
    {
        $this->registrar($solicitud, $solicitud->estudiante_id, 'Solicitud registrada');
    }

    public function updated(Solicitud $solicitud): void
    {
        if (! $solicitud->wasChanged('estado_id')) {
            return;
        }

        $actorId = $solicitud->contextoCambio['actor']->id ?? Auth::id();

        if ($actorId === null) {
            // Falla en voz alta: un cambio de estado sin responsable no es auditable.
            throw new LogicException('Todo cambio de estado necesita un usuario responsable; usa Solicitud::cambiarEstado().');
        }

        $this->registrar($solicitud, (int) $actorId, $solicitud->contextoCambio['comentario'] ?? null);
        $this->notificarAlEstudiante($solicitud, (int) $actorId);
    }

    /**
     * Contrato §7: cada cambio de estado avisa al estudiante dueño, salvo que
     * él mismo lo haya provocado. Corre en la transacción de cambiarEstado(),
     * así el aviso y el cambio se confirman o se revierten juntos. La creación
     * inicial no pasa por aquí (solo `updated`).
     */
    private function notificarAlEstudiante(Solicitud $solicitud, int $actorId): void
    {
        if ($actorId === $solicitud->estudiante_id) {
            return;
        }

        // Se recarga: la relación pudo cargarse con el estado anterior.
        $estado = str_replace('_', ' ', $solicitud->unsetRelation('estado')->estado->nombre);

        Notificacion::create([
            'usuario_id' => $solicitud->estudiante_id,
            'solicitud_id' => $solicitud->id,
            'mensaje' => "Tu solicitud «{$solicitud->titulo}» ahora está {$estado}.",
        ]);
    }

    private function registrar(Solicitud $solicitud, int $actorId, ?string $comentario): void
    {
        HistorialEstado::create([
            'solicitud_id' => $solicitud->id,
            'estado_id' => $solicitud->estado_id,
            'cambiado_por' => $actorId,
            'comentario' => $comentario,
        ]);
    }
}
