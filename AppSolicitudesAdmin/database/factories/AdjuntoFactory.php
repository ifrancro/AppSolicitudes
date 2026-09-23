<?php

namespace Database\Factories;

use App\Models\Adjunto;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjunto>
 */
class AdjuntoFactory extends Factory
{
    protected $model = Adjunto::class;

    public function definition(): array
    {
        return [
            'solicitud_id' => Solicitud::factory(),
            'url_archivo' => 'adjuntos/'.fake()->uuid().'.jpg',
            'tipo_archivo' => 'image/jpeg',
            'subido_por' => User::factory()->estudiante(),
        ];
    }
}
