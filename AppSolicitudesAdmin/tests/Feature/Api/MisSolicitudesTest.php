<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Enums\TipoNombre;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MisSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private User $estudiante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
        $this->estudiante = User::factory()->estudiante()->create();
    }

    public function test_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/mis-solicitudes')->assertStatus(401);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesNoEstudiante(): array
    {
        return [
            'personal' => ['personalAdministrativo'],
            'responsable' => ['responsable'],
            'administrador' => ['administrador'],
        ];
    }

    #[DataProvider('rolesNoEstudiante')]
    public function test_otros_roles_reciben_403(string $rol): void
    {
        Sanctum::actingAs(User::factory()->{$rol}()->create());

        $this->getJson('/api/v1/mis-solicitudes')->assertForbidden();
    }

    public function test_solo_devuelve_las_del_usuario_autenticado_sin_estudiante(): void
    {
        $propias = Solicitud::factory()->count(2)->create(['estudiante_id' => $this->estudiante->id]);
        Solicitud::factory()->count(3)->create();
        Sanctum::actingAs($this->estudiante);

        $respuesta = $this->getJson('/api/v1/mis-solicitudes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('data.0.estudiante');

        $this->assertEqualsCanonicalizing($propias->pluck('id')->all(), $respuesta->json('data.*.id'));
    }

    public function test_incluye_el_responsable_de_la_asignacion_activa(): void
    {
        $solicitud = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id, 'activo' => true]);
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes')
            ->assertOk()
            ->assertJsonPath('data.0.responsable.id', $responsable->id);
    }

    public function test_un_estudiante_no_puede_ver_las_de_otro_forzando_estudiante_id(): void
    {
        $otro = User::factory()->estudiante()->create();
        Solicitud::factory()->count(2)->create(['estudiante_id' => $otro->id]);
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes?estudiante_id='.$otro->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pagina_con_links_y_meta_del_contrato(): void
    {
        Solicitud::factory()->count(20)->create(['estudiante_id' => $this->estudiante->id]);
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes?per_page=8')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonStructure([
                'data', 'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 8)
            ->assertJsonPath('meta.total', 20);

        $this->getJson('/api/v1/mis-solicitudes?per_page=8&page=3')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('links.next', null)
            ->assertJsonPath('meta.from', 17)
            ->assertJsonPath('meta.to', 20);
    }

    public function test_el_per_page_por_defecto_es_15(): void
    {
        Solicitud::factory()->count(16)->create(['estudiante_id' => $this->estudiante->id]);
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes')
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_la_paginacion_no_repite_ni_salta_filas(): void
    {
        Solicitud::factory()->count(7)->create(['estudiante_id' => $this->estudiante->id]);
        Sanctum::actingAs($this->estudiante);

        $ids = [];
        foreach ([1, 2, 3] as $pagina) {
            $ids = [...$ids, ...$this->getJson("/api/v1/mis-solicitudes?per_page=3&page={$pagina}")->json('data.*.id')];
        }

        $this->assertCount(7, array_unique($ids));
    }

    public function test_pagina_vacia_devuelve_data_vacio_y_no_error(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);

        Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        $this->getJson('/api/v1/mis-solicitudes?page=9')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function parametrosInvalidos(): array
    {
        return [
            'per_page cero' => ['per_page=0'],
            'per_page 51' => ['per_page=51'],
            'per_page texto' => ['per_page=abc'],
            'page cero' => ['page=0'],
            'orden desconocido' => ['orden=titulo'],
            'estado inexistente' => ['estado_id=9999'],
            'tipo inexistente' => ['tipo_id=9999'],
            'fecha mal formada' => ['desde=24/09/2026'],
            'hasta anterior a desde' => ['desde=2026-09-10&hasta=2026-09-01'],
        ];
    }

    #[DataProvider('parametrosInvalidos')]
    public function test_parametros_invalidos_dan_422(string $query): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes?'.$query)->assertUnprocessable();
    }

    public function test_per_page_50_es_valido(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/mis-solicitudes?per_page=50')->assertOk()->assertJsonPath('meta.per_page', 50);
    }

    public function test_filtra_por_estado_tipo_y_titulo(): void
    {
        $cerrada = Solicitud::factory()->conEstado(EstadoNombre::Cerrada)->create(['estudiante_id' => $this->estudiante->id, 'titulo' => 'Proyector roto']);
        $infra = Solicitud::factory()->conTipo(TipoNombre::Infraestructura)->create(['estudiante_id' => $this->estudiante->id, 'titulo' => 'Gotera']);
        Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id, 'titulo' => 'Otra cosa']);
        Sanctum::actingAs($this->estudiante);

        $this->assertSame([$cerrada->id], $this->getJson('/api/v1/mis-solicitudes?estado_id='.$cerrada->estado_id)->json('data.*.id'));
        $this->assertSame([$infra->id], $this->getJson('/api/v1/mis-solicitudes?tipo_id='.$infra->tipo_id)->json('data.*.id'));
        $this->assertSame([$cerrada->id], $this->getJson('/api/v1/mis-solicitudes?q=PROYECTOR')->json('data.*.id'));
    }

    public function test_filtra_por_rango_de_fechas_inclusivo(): void
    {
        $antes = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        $dentro = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        $despues = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        DB::table('solicitudes')->where('id', $antes->id)->update(['created_at' => '2026-09-09 23:59:59']);
        DB::table('solicitudes')->where('id', $dentro->id)->update(['created_at' => '2026-09-10 08:00:00']);
        DB::table('solicitudes')->where('id', $despues->id)->update(['created_at' => '2026-09-11 00:00:01']);
        Sanctum::actingAs($this->estudiante);

        $this->assertSame([$dentro->id], $this->getJson('/api/v1/mis-solicitudes?desde=2026-09-10&hasta=2026-09-10')->json('data.*.id'));
    }

    public function test_ignora_filtros_que_no_son_del_contrato(): void
    {
        $solicitud = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        Sanctum::actingAs($this->estudiante);

        // sin_asignar y responsable_id no aplican a este endpoint.
        $this->getJson('/api/v1/mis-solicitudes?sin_asignar=1&responsable_id='.$this->estudiante->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $solicitud->id);
    }

    public function test_ordena_por_created_at_descendente_por_defecto_y_permite_ascendente(): void
    {
        $vieja = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        $nueva = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
        DB::table('solicitudes')->where('id', $vieja->id)->update(['created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00']);
        DB::table('solicitudes')->where('id', $nueva->id)->update(['created_at' => '2026-02-01 10:00:00', 'updated_at' => '2026-03-01 10:00:00']);
        Sanctum::actingAs($this->estudiante);

        $this->assertSame([$nueva->id, $vieja->id], $this->getJson('/api/v1/mis-solicitudes')->json('data.*.id'));
        $this->assertSame([$vieja->id, $nueva->id], $this->getJson('/api/v1/mis-solicitudes?orden=created_at')->json('data.*.id'));
        $this->assertSame([$vieja->id, $nueva->id], $this->getJson('/api/v1/mis-solicitudes?orden=-updated_at')->json('data.*.id'));
        $this->assertSame([$nueva->id, $vieja->id], $this->getJson('/api/v1/mis-solicitudes?orden=updated_at')->json('data.*.id'));
    }

    public function test_listar_20_no_hace_mas_consultas_que_listar_2(): void
    {
        $responsable = User::factory()->responsable()->create();
        $crear = function (int $n) use ($responsable): void {
            Solicitud::factory()->count($n)->create(['estudiante_id' => $this->estudiante->id])
                ->each(fn (Solicitud $s) => Asignacion::factory()->create(['solicitud_id' => $s->id, 'responsable_id' => $responsable->id, 'activo' => true]));
        };
        Sanctum::actingAs($this->estudiante);

        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/mis-solicitudes?per_page=50')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $crear(2);
        $contar(); // calentamiento: la primera petición actualiza last_used_at del token
        $conDos = $contar();
        $crear(18);
        $conVeinte = $contar();

        $this->assertSame(20, Solicitud::where('estudiante_id', $this->estudiante->id)->count());
        $this->assertSame($conDos, $conVeinte, "Consultas con 2: {$conDos}; con 20: {$conVeinte}");
    }
}
