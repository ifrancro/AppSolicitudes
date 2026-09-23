<?php

namespace Database\Seeders;

use App\Enums\RolNombre;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Un usuario por rol para probar a mano. Contraseña común: "password".
 * Solo para entornos de desarrollo; no ejecutar en producción.
 */
class UsuariosPruebaSeeder extends Seeder
{
    public const CUENTAS = [
        ['Estudiante Demo', 'estudiante@campus.test', RolNombre::Estudiante],
        ['Personal Administrativo Demo', 'personal@campus.test', RolNombre::PersonalAdministrativo],
        ['Responsable Demo', 'responsable@campus.test', RolNombre::Responsable],
        ['Administrador Demo', 'admin@campus.test', RolNombre::Administrador],
    ];

    public function run(): void
    {
        foreach (self::CUENTAS as [$nombre, $email, $rol]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'nombre' => $nombre,
                    'password_hash' => 'password',
                    'rol_id' => Rol::where('nombre', $rol->value)->value('id'),
                    'activo' => true,
                ],
            );
        }
    }
}
