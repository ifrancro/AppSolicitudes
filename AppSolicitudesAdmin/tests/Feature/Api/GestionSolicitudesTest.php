<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Enums\TipoNombre;
use App\Models\Asignacion;
use App\Models\Prioridad;
use App\Models\Solicitud;
use App\Models\TipoSolicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Contrato 6.1 (listado global) y 6.2 (clasificacion).
 */
class GestionSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    // --- GET /solicitudes ---

    public function test_personal_y_administrador_listan_todas_con_estudiante_y_responsable(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);
        Solicitud::factory()->count(2)->create();

        foreach ([User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create()] as $usuario) {
            Sanctum::actingAs($usuario);

            $this->getJson('/api/v1/solicitudes')
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('meta.total', 3)
                ->assertJsonStructure(['data' => [['id', 'titulo', 'estudiante' => ['id', 'nombre'], 'responsable', 'tipo', 'prioridad', 'estado']], 'links', 'meta']);
        }

        $this->getJson('/api/v1/solicitudes?responsable_id='.$responsable->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.responsable.id', $responsable->id);
    }

    public function test_listado_global_deniega_a_estudiante_y_responsable(): void
    {
        foreach ([User::factory()->estudiante()->create(), User::factory()->responsable()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/solicitudes')->assertForbidden();
        }
    }

    public function test_403_gana_sobre_422_en_el_listado(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->getJson('/api/v1/solicitudes?per_page=999')->assertForbidden();
    }

    public function test_listado_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/solicitudes')->assertUnauthorized();
    }

    public function test_listado_filtra_por_estado_tipo_prioridad_estudiante_sin_asignar_y_texto(): void
    {
        Sanctum::actingAs(User::factory()->personalAdministrativo()->create());

        $estudiante = User::factory()->estudiante()->create();
        $objetivo = Solicitud::factory()
            ->conEstado(EstadoNombre::EnProceso)->conTipo(TipoNombre::Infraestructura)->conPrioridad(PrioridadNombre::Alta)
            ->create(['estudiante_id' => $estudiante->id, 'titulo' => 'Gotera en el techo']);
        Solicitud::factory()->count(2)->create();
        Asignacion::factory()->create(['solicitud_id' => Solicitud::first()->id]);

        $this->getJson('/api/v1/solicitudes?estado_id='.$objetivo->estado_id)->assertJsonPath('data.0.id', $objetivo->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/solicitudes?tipo_id='.$objetivo->tipo_id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/solicitudes?prioridad_id='.$objetivo->prioridad_id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/solicitudes?estudiante_id='.$estudiante->id)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/solicitudes?q=GOTERA')->assertJsonCount(1, 'data');
        // 3 solicitudes, 1 con asignacion activa => 2 sin asignar.
        $this->getJson('/api/v1/solicitudes?sin_asignar=1')->assertJsonCount(2, 'data');
    }

    public function test_listado_ordena_y_pagina(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        Solicitud::factory()->count(5)->create();

        $this->getJson('/api/v1/solicitudes?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('links.next', null);

        $ids = $this->getJson('/api/v1/solicitudes?orden=created_at&per_page=50')->json('data.*.id');
        $this->assertSame($ids, collect($ids)->sort()->values()->all());
    }

    public function test_listado_valida_filtros_con_422(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->getJson('/api/v1/solicitudes?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/solicitudes?orden=titulo')->assertStatus(422)->assertJsonValidationErrors('orden');
        $this->getJson('/api/v1/solicitudes?estado_id=999')->assertStatus(422)->assertJsonValidationErrors('estado_id');
        $this->getJson('/api/v1/solicitudes?desde=2026-05-10&hasta=2026-05-01')->assertStatus(422)->assertJsonValidationErrors('hasta');
    }

    public function test_listado_no_hace_consultas_extra_por_fila(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/solicitudes?per_page=50')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->crearSolicitudesAsignadas(2);
        $contar(); // calienta cachés y conexiones
        $conDos = $contar();

        $this->crearSolicitudesAsignadas(18);
        $conVeinte = $contar();

        $this->assertSame($conDos, $conVeinte);
    }

    // --- PATCH /solicitudes/{id}/clasificacion ---

    public function test_personal_y_administrador_clasifican(): void
    {
        $tipo = TipoSolicitud::where('nombre', TipoNombre::Equipamiento->value)->first();
        $prioridad = Prioridad::where('nombre', PrioridadNombre::Urgente->value)->first();

        foreach ([User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $solicitud = Solicitud::factory()->create();

            $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['tipo_id' => $tipo->id, 'prioridad_id' => $prioridad->id])
                ->assertOk()
                ->assertJsonPath('data.tipo.nombre', 'equipamiento')
                ->assertJsonPath('data.prioridad.nombre', 'urgente')
                ->assertJsonPath('data.estudiante.id', $solicitud->estudiante_id);
        }
    }

    public function test_se_puede_clasificar_solo_uno_de_los_dos_campos(): void
    {
        Sanctum::actingAs(User::factory()->personalAdministrativo()->create());
        $solicitud = Solicitud::factory()->create();
        $prioridad = Prioridad::where('nombre', PrioridadNombre::Alta->value)->first();

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['prioridad_id' => $prioridad->id])
            ->assertOk()
            ->assertJsonPath('data.prioridad.nombre', 'alta')
            ->assertJsonPath('data.tipo.id', $solicitud->tipo_id);

        $this->assertSame($solicitud->tipo_id, $solicitud->fresh()->tipo_id);
    }

    public function test_clasificar_deniega_a_estudiante_y_responsable_incluso_asignado(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);
        $tipo = TipoSolicitud::first();

        foreach ([$solicitud->estudiante, $responsable] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['tipo_id' => $tipo->id])->assertForbidden();
        }

        $this->assertSame($solicitud->tipo_id, $solicitud->fresh()->tipo_id);
    }

    public function test_clasificar_valida_con_422(): void
    {
        Sanctum::actingAs(User::factory()->personalAdministrativo()->create());
        $solicitud = Solicitud::factory()->create();

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_id', 'prioridad_id']);

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['tipo_id' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('tipo_id');

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['prioridad_id' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('prioridad_id');
    }

    public function test_no_se_clasifica_una_solicitud_cerrada_o_cancelada(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $tipo = TipoSolicitud::first();

        foreach ([EstadoNombre::Cerrada, EstadoNombre::Cancelada] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create();

            $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/clasificacion", ['tipo_id' => $tipo->id])
                ->assertStatus(422)
                ->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_clasificar_inexistente_da_404_y_sin_token_401(): void
    {
        $this->patchJson('/api/v1/solicitudes/1/clasificacion', ['tipo_id' => 1])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->patchJson('/api/v1/solicitudes/99999/clasificacion', ['tipo_id' => 1])->assertNotFound();
    }

    public function test_reasignar_quita_el_acceso_de_lectura_al_responsable_anterior(): void
    {
        $solicitud = Solicitud::factory()->create();
        $anterior = User::factory()->responsable()->create();
        $nuevo = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $anterior->id]);

        $this->assertTrue(Gate::forUser($anterior)->allows('view', $solicitud));

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $nuevo->id])->assertCreated();

        $this->assertFalse(Gate::forUser($anterior->fresh())->allows('view', $solicitud));
        $this->assertTrue(Gate::forUser($nuevo)->allows('view', $solicitud));
    }

    private function crearSolicitudesAsignadas(int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $solicitud = Solicitud::factory()->create();
            Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
        }
    }
}
