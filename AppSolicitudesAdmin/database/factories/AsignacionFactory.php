<?php

namespace Database\Factories;

use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asignacion>
 */
class AsignacionFactory extends Factory
{
    protected $model = Asignacion::class;

    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory(),
            'responsable_id' => User::factory()->responsable(),
            'asignado_por' => User::factory()->administrador(),
            'activo' => true,
            'fecha_asignacion' => now(),
            'fecha_fin' => null,
        ];
    }

    public function finalizada(): static
    {
        return $this->state(fn () => ['activo' => false, 'fecha_fin' => now()]);
    }
}
