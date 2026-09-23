<?php

namespace Database\Factories;

use App\Enums\RolNombre;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'password',
            'rol_id' => fn () => Rol::firstOrCreate(['nombre' => RolNombre::Estudiante->value])->id,
            'activo' => true,
        ];
    }

    public function conRol(RolNombre $rol): static
    {
        return $this->state(fn () => [
            'rol_id' => Rol::firstOrCreate(['nombre' => $rol->value])->id,
        ]);
    }

    public function estudiante(): static
    {
        return $this->conRol(RolNombre::Estudiante);
    }

    public function personalAdministrativo(): static
    {
        return $this->conRol(RolNombre::PersonalAdministrativo);
    }

    public function responsable(): static
    {
        return $this->conRol(RolNombre::Responsable);
    }

    public function administrador(): static
    {
        return $this->conRol(RolNombre::Administrador);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
