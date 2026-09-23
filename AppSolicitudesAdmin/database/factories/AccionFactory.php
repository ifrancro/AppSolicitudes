<?php

namespace Database\Factories;

use App\Models\Accion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accion>
 */
class AccionFactory extends Factory
{
    protected $model = Accion::class;

    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory(),
            'responsable_id' => User::factory()->responsable(),
            'descripcion' => fake()->sentence(),
        ];
    }
}
