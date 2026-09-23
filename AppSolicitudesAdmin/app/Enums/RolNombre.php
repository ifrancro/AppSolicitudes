<?php

namespace App\Enums;

/**
 * Nombres exactos de la tabla `roles`. El cliente móvil los compara por texto.
 */
enum RolNombre: string
{
    case Estudiante = 'estudiante';
    case PersonalAdministrativo = 'personal_administrativo';
    case Responsable = 'responsable';
    case Administrador = 'administrador';

    /**
     * Roles que gestionan solicitudes desde el panel web.
     *
     * @return array<int, self>
     */
    public static function gestion(): array
    {
        return [self::PersonalAdministrativo, self::Responsable, self::Administrador];
    }
}
