<?php

namespace Database\Factories;

use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notificacion>
 */
class NotificacionFactory extends Factory
{
    protected $model = Notificacion::class;

    public function definition(): array
    {
        return [
            'usuario_id' => User::factory()->estudiante(),
            'solicitud_id' => Solicitud::factory(),
            'mensaje' => fake()->sentence(),
            'leido' => false,
        ];
    }

    public function leida(): static
    {
        return $this->state(fn () => ['leido' => true]);
    }
}
