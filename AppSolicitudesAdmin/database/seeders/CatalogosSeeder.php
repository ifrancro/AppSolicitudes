<?php

namespace Database\Seeders;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Enums\RolNombre;
use App\Enums\TipoNombre;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Rol;
use App\Models\TipoSolicitud;
use Illuminate\Database\Seeder;

/**
 * Datos de referencia que el sistema necesita en cualquier entorno.
 * Es idempotente: se puede ejecutar varias veces.
 */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RolNombre::cases() as $rol) {
            Rol::firstOrCreate(['nombre' => $rol->value]);
        }

        foreach (TipoNombre::cases() as $tipo) {
            TipoSolicitud::updateOrCreate(
                ['nombre' => $tipo->value],
                ['descripcion' => $tipo->descripcion()],
            );
        }

        foreach (PrioridadNombre::cases() as $prioridad) {
            Prioridad::updateOrCreate(
                ['nombre' => $prioridad->value],
                ['nivel' => $prioridad->nivel()],
            );
        }

        foreach (EstadoNombre::cases() as $estado) {
            EstadoSolicitud::firstOrCreate(['nombre' => $estado->value]);
        }
    }
}
