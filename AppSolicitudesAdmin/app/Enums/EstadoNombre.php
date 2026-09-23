<?php

namespace App\Enums;

/**
 * Nombres exactos de la tabla `estados_solicitud`.
 */
enum EstadoNombre: string
{
    case Pendiente = 'pendiente';
    case Asignada = 'asignada';
    case EnProceso = 'en_proceso';
    case Cerrada = 'cerrada';
    case Cancelada = 'cancelada';

    /**
     * Estados a los que se puede llegar desde este (contrato §6.7). Una sola
     * tabla para la API y para el panel web.
     *
     * @return array<int, self>
     */
    public function transicionesLegales(): array
    {
        return match ($this) {
            self::Pendiente => [self::Asignada, self::Cancelada],
            self::Asignada => [self::EnProceso, self::Cancelada],
            self::EnProceso => [self::Cerrada, self::Cancelada],
            self::Cerrada, self::Cancelada => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transicionesLegales(), true);
    }

    /** Cerrada y cancelada no admiten ningún cambio. */
    public function esFinal(): bool
    {
        return $this->transicionesLegales() === [];
    }
}
