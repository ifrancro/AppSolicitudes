<?php

namespace App\Enums;

/**
 * Nombres exactos de la tabla `prioridades`; el valor numérico es su `nivel`.
 */
enum PrioridadNombre: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function nivel(): int
    {
        return match ($this) {
            self::Baja => 1,
            self::Media => 2,
            self::Alta => 3,
            self::Urgente => 4,
        };
    }
}
