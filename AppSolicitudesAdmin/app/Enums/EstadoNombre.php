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
}
