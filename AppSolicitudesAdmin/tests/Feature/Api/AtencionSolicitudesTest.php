<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Models\Accion;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\AccionService;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Contrato §6.6 (bandeja), §6.8 y §6.9 (acciones).
 */
class AtencionSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    private function asignar(Solicitud $solicitud, User $responsable, bool $activa = true): Asignacion
    {
        $fabrica = Asignacion::factory();

        return ($activa ? $fabrica : $fabrica->finalizada())
            ->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);
    }

    // --- GET /solicitudes-asignadas ---

    public function test_el_responsable_ve_solo_sus_solicitudes_con_asignacion_activa(): void
    {
        $yo = User::factory()->responsable()->create();
        $mia = Solicitud::factory()->create();
        $finalizada = Solicitud::factory()->create();
        $ajena = Solicitud::factory()->create();
        Solicitud::factory()->create();

        $this->asignar($mia, $yo);
        $this->asignar($finalizada, $yo, activa: false);
        $this->asignar($finalizada, User::factory()->responsable()->create());
        $this->asignar($ajena, User::factory()->responsable()->create());

        Sanctum::actingAs($yo);

        $this->getJson('/api/v1/solicitudes-asignadas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mia->id)
            ->assertJsonPath('data.0.estudiante.id', $mia->estudiante_id)
            ->assertJsonPath('data.0.responsable.id', $yo->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data' => [['id', 'titulo', 'tipo', 'prioridad', 'estado', 'estudiante']], 'links', 'meta']);
    }

    public function test_la_bandeja_filtra_por_estado_prioridad_texto_y_ordena(): void
    {
        $yo = User::factory()->responsable()->create();
        $objetivo = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->conPrioridad(PrioridadNombre::Urgente)
            ->create(['titulo' => 'Proyector dañado', 'created_at' => now()->subDay()]);
        $otra = Solicitud::factory()->create(['titulo' => 'Aire acondicionado', 'created_at' => now()]);
        $this->asignar($objetivo, $yo);
        $this->asignar($otra, $yo);
        Sanctum::actingAs($yo);

        $this->getJson('/api/v1/solicitudes-asignadas?estado_id='.$objetivo->estado_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $objetivo->id);
        $this->getJson('/api/v1/solicitudes-asignadas?prioridad_id='.$objetivo->prioridad_id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $objetivo->id);
        $this->getJson('/api/v1/solicitudes-asignadas?q=PROYECTOR')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $objetivo->id);
        $this->getJson('/api/v1/solicitudes-asignadas?orden=created_at')->assertJsonPath('data.0.id', $objetivo->id);
        $this->getJson('/api/v1/solicitudes-asignadas')->assertJsonPath('data.0.id', $otra->id);
    }

    public function test_los_filtros_ajenos_al_contrato_no_amplian_la_bandeja(): void
    {
        $yo = User::factory()->responsable()->create();
        $ajena = Solicitud::factory()->create();
        $otro = User::factory()->responsable()->create();
        $this->asignar($ajena, $otro);
        Sanctum::actingAs($yo);

        $this->getJson('/api/v1/solicitudes-asignadas?responsable_id='.$otro->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/solicitudes-asignadas?sin_asignar=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_la_bandeja_pagina_y_valida_filtros(): void
    {
        $yo = User::factory()->responsable()->create();
        Solicitud::factory()->count(3)->create()->each(fn ($s) => $this->asignar($s, $yo));
        Sanctum::actingAs($yo);

        $this->getJson('/api/v1/solicitudes-asignadas?per_page=2&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('links.next', null);
        $this->getJson('/api/v1/solicitudes-asignadas?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/solicitudes-asignadas?orden=titulo')->assertStatus(422)->assertJsonValidationErrors('orden');
    }

    public function test_la_bandeja_se_deniega_a_estudiante_personal_y_administrador(): void
    {
        foreach ([User::factory()->estudiante()->create(), User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/solicitudes-asignadas')->assertForbidden();
            // El 403 gana sobre un 422 de filtros.
            $this->getJson('/api/v1/solicitudes-asignadas?per_page=999')->assertForbidden();
        }
    }

    public function test_la_bandeja_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/solicitudes-asignadas')->assertUnauthorized();
    }

    public function test_la_bandeja_no_hace_consultas_extra_por_fila(): void
    {
        $yo = User::factory()->responsable()->create();
        Sanctum::actingAs($yo);

        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/solicitudes-asignadas?per_page=50')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        Solicitud::factory()->count(2)->create()->each(fn ($s) => $this->asignar($s, $yo));
        $contar();
        $conDos = $contar();

        Solicitud::factory()->count(18)->create()->each(fn ($s) => $this->asignar($s, $yo));
        $conVeinte = $contar();

        $this->assertSame($conDos, $conVeinte);
    }

    // --- POST /solicitudes/{id}/acciones ---

    public function test_el_responsable_asignado_registra_una_accion_en_asignada_y_en_proceso(): void
    {
        $yo = User::factory()->responsable()->create();
        Sanctum::actingAs($yo);

        foreach ([EstadoNombre::Asignada, EstadoNombre::EnProceso] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create();
            $this->asignar($solicitud, $yo);

            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/acciones", ['descripcion' => 'Se reemplazó la lámpara.'])
                ->assertCreated()
                ->assertJsonPath('data.solicitud_id', $solicitud->id)
                ->assertJsonPath('data.responsable', ['id' => $yo->id, 'nombre' => $yo->nombre])
                ->assertJsonPath('data.descripcion', 'Se reemplazó la lámpara.')
                ->assertJsonStructure(['data' => ['id', 'created_at']]);

            $this->assertSame(1, Accion::where('solicitud_id', $solicitud->id)->count());
        }
    }

    public function test_no_se_registran_acciones_en_pendiente_cerrada_o_cancelada(): void
    {
        $yo = User::factory()->responsable()->create();
        Sanctum::actingAs($yo);

        foreach ([EstadoNombre::Pendiente, EstadoNombre::Cerrada, EstadoNombre::Cancelada] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create();
            $this->asignar($solicitud, $yo);

            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/acciones", ['descripcion' => 'Algo'])
                ->assertStatus(422)
                ->assertJsonStructure(['message', 'errors']);

            $this->assertSame(0, Accion::where('solicitud_id', $solicitud->id)->count());
        }
    }

    public function test_registrar_accion_se_deniega_a_admin_personal_estudiante_otro_responsable_y_al_anterior(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        $anterior = User::factory()->responsable()->create();
        $this->asignar($solicitud, $anterior, activa: false);
        $this->asignar($solicitud, User::factory()->responsable()->create());

        $denegados = [
            User::factory()->administrador()->create(),
            User::factory()->personalAdministrativo()->create(),
            $solicitud->estudiante,
            User::factory()->responsable()->create(),
            $anterior,
        ];

        foreach ($denegados as $usuario) {
            Sanctum::actingAs($usuario);
            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/acciones", ['descripcion' => 'Intento'])->assertForbidden();
        }

        $this->assertSame(0, Accion::count());
    }

    public function test_registrar_accion_valida_401_y_404(): void
    {
        $this->postJson('/api/v1/solicitudes/1/acciones', ['descripcion' => 'x'])->assertUnauthorized();

        $yo = User::factory()->responsable()->create();
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        $this->asignar($solicitud, $yo);
        Sanctum::actingAs($yo);

        $this->postJson('/api/v1/solicitudes/99999/acciones', ['descripcion' => 'x'])->assertNotFound();
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/acciones", [])
            ->assertStatus(422)->assertJsonValidationErrors('descripcion');
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/acciones", ['descripcion' => str_repeat('a', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors('descripcion');
    }

    // --- GET /solicitudes/{id}/acciones ---

    public function test_personal_admin_y_responsable_asignado_ven_las_acciones_mas_recientes_primero(): void
    {
        $yo = User::factory()->responsable()->create();
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        $this->asignar($solicitud, $yo);
        $primera = Accion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $yo->id, 'created_at' => now()->subHour()]);
        $segunda = Accion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $yo->id, 'created_at' => now()]);

        foreach ([User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create(), $yo] as $usuario) {
            Sanctum::actingAs($usuario);

            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/acciones")
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.id', $segunda->id)
                ->assertJsonPath('data.1.id', $primera->id)
                ->assertJsonPath('data.0.responsable.id', $yo->id);
        }
    }

    public function test_ver_acciones_se_deniega_a_estudiante_otro_responsable_y_al_anterior(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        $anterior = User::factory()->responsable()->create();
        $this->asignar($solicitud, $anterior, activa: false);
        $this->asignar($solicitud, User::factory()->responsable()->create());

        foreach ([$solicitud->estudiante, User::factory()->responsable()->create(), $anterior] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/acciones")->assertForbidden();
        }
    }

    public function test_ver_acciones_da_401_y_404(): void
    {
        $this->getJson('/api/v1/solicitudes/1/acciones')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->getJson('/api/v1/solicitudes/99999/acciones')->assertNotFound();
    }

    public function test_ver_acciones_no_hace_consultas_extra_por_fila(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();

        $contar = function () use ($solicitud): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/acciones")->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        Accion::factory()->count(2)->create(['solicitud_id' => $solicitud->id]);
        $contar();
        $conDos = $contar();

        Accion::factory()->count(18)->create(['solicitud_id' => $solicitud->id]);
        $conVeinte = $contar();

        $this->assertSame($conDos, $conVeinte);
    }

    // --- Servicio, sin HTTP ---

    public function test_el_servicio_de_acciones_funciona_sin_http(): void
    {
        $yo = User::factory()->responsable()->create();
        $servicio = app(AccionService::class);

        $accion = $servicio->registrar(Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create(), $yo, 'Revisión inicial');
        $this->assertSame('Revisión inicial', $accion->descripcion);

        $this->expectException(ValidationException::class);
        $servicio->registrar(Solicitud::factory()->conEstado(EstadoNombre::Cerrada)->create(), $yo, 'Tarde');
    }
}
