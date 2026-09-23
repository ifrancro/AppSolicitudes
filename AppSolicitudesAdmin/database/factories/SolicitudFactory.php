<?php

namespace Database\Factories;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Enums\TipoNombre;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Solicitud;
use App\Models\TipoSolicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitud>
 */
class SolicitudFactory extends Factory
{
    protected $model = Solicitud::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => fn () => User::factory()->estudiante()->create()->id,
            'tipo_id' => fn () => TipoSolicitud::firstOrCreate(['nombre' => TipoNombre::Mantenimiento->value])->id,
            'prioridad_id' => fn () => Prioridad::firstOrCreate(
                ['nombre' => PrioridadNombre::Media->value],
                ['nivel' => PrioridadNombre::Media->nivel()],
            )->id,
            'estado_id' => fn () => EstadoSolicitud::firstOrCreate(['nombre' => EstadoNombre::Pendiente->value])->id,
            'titulo' => fake()->sentence(4),
            'descripcion' => fake()->paragraph(),
            'ubicacion' => fake()->optional()->bothify('Edificio ? - Aula ##'),
        ];
    }

    public function conEstado(EstadoNombre $estado): static
    {
        return $this->state(fn () => [
            'estado_id' => EstadoSolicitud::firstOrCreate(['nombre' => $estado->value])->id,
        ]);
    }

    public function conTipo(TipoNombre $tipo): static
    {
        return $this->state(fn () => [
            'tipo_id' => TipoSolicitud::firstOrCreate(['nombre' => $tipo->value])->id,
        ]);
    }

    public function conPrioridad(PrioridadNombre $prioridad): static
    {
        return $this->state(fn () => [
            'prioridad_id' => Prioridad::firstOrCreate(
                ['nombre' => $prioridad->value],
                ['nivel' => $prioridad->nivel()],
            )->id,
        ]);
    }
}
