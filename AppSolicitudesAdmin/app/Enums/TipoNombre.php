<?php

namespace App\Enums;

/**
 * Nombres exactos de la tabla `tipos_solicitud`.
 */
enum TipoNombre: string
{
    case Mantenimiento = 'mantenimiento';
    case SoporteTecnologico = 'soporte_tecnologico';
    case Infraestructura = 'infraestructura';
    case Equipamiento = 'equipamiento';
    case Otros = 'otros';

    public function descripcion(): string
    {
        return match ($this) {
            self::Mantenimiento => 'Reparaciones y mantenimiento de instalaciones del campus.',
            self::SoporteTecnologico => 'Problemas con equipos, redes, cuentas o software.',
            self::Infraestructura => 'Fallas o mejoras en edificios, aulas y espacios comunes.',
            self::Equipamiento => 'Solicitud, reposición o reparación de mobiliario y equipos.',
            self::Otros => 'Cualquier requerimiento que no encaje en las otras categorías.',
        };
    }
}
