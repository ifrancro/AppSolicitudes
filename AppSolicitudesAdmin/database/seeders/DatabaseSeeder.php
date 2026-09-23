<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogosSeeder::class,
            UsuariosPruebaSeeder::class,
        ]);

        // Solicitudes de ejemplo: solo en desarrollo, nunca en pruebas ni producción.
        if (app()->environment('local')) {
            $this->call(DemoSolicitudesSeeder::class);
        }
    }
}
