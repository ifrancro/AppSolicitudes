<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function rutas(): array
    {
        return [
            'tipos' => ['/api/v1/tipos-solicitud'],
            'prioridades' => ['/api/v1/prioridades'],
            'estados' => ['/api/v1/estados-solicitud'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'estudiante' => ['estudiante'],
            'personal' => ['personalAdministrativo'],
            'responsable' => ['responsable'],
            'administrador' => ['administrador'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_sin_token_da_401(string $ruta): void
    {
        $this->seed(CatalogosSeeder::class);

        $this->getJson($ruta)
            ->assertStatus(401)
            ->assertJsonPath('message', 'No autenticado.');
    }

    #[DataProvider('roles')]
    public function test_todo_rol_autenticado_recibe_los_tres_catalogos(string $rol): void
    {
        $this->seed(CatalogosSeeder::class);
        Sanctum::actingAs(User::factory()->{$rol}()->create());

        $this->getJson('/api/v1/tipos-solicitud')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'nombre', 'descripcion']]]);

        $this->getJson('/api/v1/prioridades')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'nombre', 'nivel']]]);

        $this->getJson('/api/v1/estados-solicitud')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'nombre']]]);
    }

    public function test_la_forma_es_exacta_sin_campos_de_mas(): void
    {
        $this->seed(CatalogosSeeder::class);
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->assertSame(['id', 'nombre', 'descripcion'], array_keys($this->getJson('/api/v1/tipos-solicitud')->json('data.0')));
        $this->assertSame(['id', 'nombre', 'nivel'], array_keys($this->getJson('/api/v1/prioridades')->json('data.0')));
        $this->assertSame(['id', 'nombre'], array_keys($this->getJson('/api/v1/estados-solicitud')->json('data.0')));
        $this->assertSame(['data'], array_keys($this->getJson('/api/v1/estados-solicitud')->json()));
    }

    public function test_los_valores_sembrados_coinciden_con_el_contrato(): void
    {
        $this->seed(CatalogosSeeder::class);
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->assertEqualsCanonicalizing(
            ['mantenimiento', 'soporte_tecnologico', 'infraestructura', 'equipamiento', 'otros'],
            $this->getJson('/api/v1/tipos-solicitud')->json('data.*.nombre'),
        );
    }

    public function test_prioridades_van_ordenadas_por_nivel(): void
    {
        $this->seed(CatalogosSeeder::class);
        Sanctum::actingAs(User::factory()->administrador()->create());

        $respuesta = $this->getJson('/api/v1/prioridades')->assertOk();

        $this->assertSame(['baja', 'media', 'alta', 'urgente'], $respuesta->json('data.*.nombre'));
        $this->assertSame([1, 2, 3, 4], $respuesta->json('data.*.nivel'));
    }

    public function test_estados_van_ordenados_por_id_en_el_orden_del_flujo(): void
    {
        $this->seed(CatalogosSeeder::class);
        Sanctum::actingAs(User::factory()->responsable()->create());

        $respuesta = $this->getJson('/api/v1/estados-solicitud')->assertOk();

        $this->assertSame(['pendiente', 'asignada', 'en_proceso', 'cerrada', 'cancelada'], $respuesta->json('data.*.nombre'));
        $ids = $respuesta->json('data.*.id');
        $ordenados = $ids;
        sort($ordenados);
        $this->assertSame($ordenados, $ids);
    }

    public function test_catalogo_vacio_devuelve_lista_vacia_y_no_error(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->getJson('/api/v1/prioridades')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_metodos_de_escritura_no_estan_permitidos(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson('/api/v1/prioridades', [])->assertStatus(405)->assertJsonPath('message', 'Método no permitido.');
    }
}
