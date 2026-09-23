<?php

namespace Tests\Feature\Database;

use App\Models\Solicitud;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSolicitudesSeeder;
use Database\Seeders\UsuariosPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSolicitudesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_solicitudes_en_todos_los_estados_una_sola_vez(): void
    {
        $this->seed([CatalogosSeeder::class, UsuariosPruebaSeeder::class]);

        $this->seed(DemoSolicitudesSeeder::class);
        $total = Solicitud::count();
        $this->seed(DemoSolicitudesSeeder::class); // idempotente

        $this->assertSame($total, Solicitud::count());
        $this->assertGreaterThanOrEqual(9, $total);
        $this->assertSame(5, Solicitud::with('estado')->get()->pluck('estado.nombre')->unique()->count());
    }

    public function test_el_seeder_general_no_crea_datos_de_ejemplo_fuera_de_local(): void
    {
        $this->seed();

        $this->assertSame(0, Solicitud::count());
    }
}
