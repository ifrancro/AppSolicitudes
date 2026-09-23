<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\HistorialEstado;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\AsignacionService;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Contrato 6.3 (responsables), 6.4 (asignar) y 6.5 (historial de asignaciones).
 */
class AsignacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    // --- GET /usuarios/responsables ---

    public function test_lista_solo_responsables_activos_ordenados_por_carga_y_nombre(): void
    {
        $ana = User::factory()->responsable()->create(['nombre' => 'Ana']);
        $beto = User::factory()->responsable()->create(['nombre' => 'Beto']);
        $carla = User::factory()->responsable()->create(['nombre' => 'Carla']);
        User::factory()->responsable()->inactivo()->create(['nombre' => 'Inactivo']);
        User::factory()->estudiante()->create();
        User::factory()->administrador()->create();

        Asignacion::factory()->count(2)->create(['responsable_id' => $ana->id]);
        Asignacion::factory()->create(['responsable_id' => $carla->id]);
        // Una asignacion finalizada no cuenta como carga.
        Asignacion::factory()->finalizada()->create(['responsable_id' => $beto->id]);

        Sanctum::actingAs(User::factory()->personalAdministrativo()->create());

        $this->getJson('/api/v1/usuarios/responsables')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0', ['id' => $beto->id, 'nombre' => 'Beto', 'email' => $beto->email, 'asignaciones_activas' => 0])
            ->assertJsonPath('data.1.nombre', 'Carla')
            ->assertJsonPath('data.1.asignaciones_activas', 1)
            ->assertJsonPath('data.2.nombre', 'Ana')
            ->assertJsonPath('data.2.asignaciones_activas', 2);
    }

    public function test_responsables_deniega_a_estudiante_y_responsable_y_exige_token(): void
    {
        $this->getJson('/api/v1/usuarios/responsables')->assertUnauthorized();

        foreach ([User::factory()->estudiante()->create(), User::factory()->responsable()->create()] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson('/api/v1/usuarios/responsables')->assertForbidden();
        }

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->getJson('/api/v1/usuarios/responsables')->assertOk();
    }

    public function test_responsables_calcula_la_carga_con_una_consulta_sin_n_mas_1(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/usuarios/responsables')->assertOk();
            $consultas = collect(DB::getQueryLog())
                ->filter(fn ($q) => str_contains($q['query'], 'from "usuarios"') || str_contains($q['query'], 'from "asignaciones"'));
            DB::disableQueryLog();

            return $consultas->count();
        };

        User::factory()->responsable()->count(2)->create();
        $contar(); // calienta cachés y conexiones
        $conDos = $contar();

        User::factory()->responsable()->count(18)->create()->each(
            fn ($r) => Asignacion::factory()->create(['responsable_id' => $r->id])
        );
        $conVeinte = $contar();

        $this->assertSame($conDos, $conVeinte);
        $this->assertSame(1, $conVeinte);
    }

    // --- POST /solicitudes/{id}/asignaciones ---

    public function test_asignar_crea_la_asignacion_y_pasa_la_solicitud_a_asignada_con_historial(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();
        $admin = User::factory()->administrador()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $responsable->id, 'comentario' => 'Urgente, revisar hoy'])
            ->assertCreated()
            ->assertJsonPath('data.solicitud_id', $solicitud->id)
            ->assertJsonPath('data.responsable', ['id' => $responsable->id, 'nombre' => $responsable->nombre])
            ->assertJsonPath('data.asignado_por.id', $admin->id)
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.fecha_fin', null)
            ->assertJsonStructure(['data' => ['id', 'fecha_asignacion']]);

        $this->assertSame(EstadoNombre::Asignada->value, $solicitud->fresh()->estado->nombre);

        $fila = HistorialEstado::where('solicitud_id', $solicitud->id)->latest('id')->first();
        $this->assertSame(EstadoNombre::Asignada->value, $fila->estado->nombre);
        $this->assertSame($admin->id, $fila->cambiado_por);
        $this->assertSame('Urgente, revisar hoy', $fila->comentario);
    }

    public function test_asignar_sin_comentario_deja_el_comentario_del_historial_en_null(): void
    {
        $solicitud = Solicitud::factory()->create();
        Sanctum::actingAs(User::factory()->personalAdministrativo()->create());

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => User::factory()->responsable()->create()->id])
            ->assertCreated();

        $this->assertNull(HistorialEstado::where('solicitud_id', $solicitud->id)->latest('id')->first()->comentario);
    }

    public function test_reasignar_cierra_la_anterior_sin_borrarla_y_el_historial_muestra_ambas(): void
    {
        $solicitud = Solicitud::factory()->create();
        $primero = User::factory()->responsable()->create();
        $segundo = User::factory()->responsable()->create();
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $primero->id])->assertCreated();
        $this->travel(1)->hours();
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $segundo->id])->assertCreated();

        $this->assertSame(2, Asignacion::where('solicitud_id', $solicitud->id)->count());
        $this->assertSame(1, Asignacion::where('solicitud_id', $solicitud->id)->where('activo', true)->count());

        $anterior = Asignacion::where('responsable_id', $primero->id)->first();
        $this->assertFalse($anterior->activo);
        $this->assertNotNull($anterior->fecha_fin);

        // La reasignacion no vuelve a cambiar el estado: sigue asignada, con una sola entrada de historial.
        $this->assertSame(EstadoNombre::Asignada->value, $solicitud->fresh()->estado->nombre);
        $this->assertSame(2, HistorialEstado::where('solicitud_id', $solicitud->id)->count());

        $this->getJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.responsable.id', $segundo->id)
            ->assertJsonPath('data.0.activo', true)
            ->assertJsonPath('data.0.fecha_fin', null)
            ->assertJsonPath('data.1.responsable.id', $primero->id)
            ->assertJsonPath('data.1.activo', false)
            ->assertJsonPath('data.1.fecha_fin', fn ($v) => $v !== null);
    }

    public function test_solo_puede_haber_una_asignacion_activa_por_solicitud(): void
    {
        $solicitud = Solicitud::factory()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);

        $this->expectException(QueryException::class);
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
    }

    public function test_asignar_a_alguien_que_no_es_responsable_da_422(): void
    {
        $solicitud = Solicitud::factory()->create();
        Sanctum::actingAs(User::factory()->administrador()->create());

        foreach ([User::factory()->estudiante()->create(), User::factory()->administrador()->create(), User::factory()->personalAdministrativo()->create()] as $usuario) {
            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $usuario->id])
                ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
        }

        $this->assertSame(0, Asignacion::count());
    }

    public function test_asignar_a_un_responsable_inactivo_o_inexistente_da_422(): void
    {
        $solicitud = Solicitud::factory()->create();
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => User::factory()->responsable()->inactivo()->create()->id])
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => 99999])
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", [])
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');
        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => User::factory()->responsable()->create()->id, 'comentario' => str_repeat('a', 501)])
            ->assertStatus(422)->assertJsonValidationErrors('comentario');
    }

    public function test_asignar_al_responsable_que_ya_la_tiene_da_422(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $responsable->id])
            ->assertStatus(422)->assertJsonValidationErrors('responsable_id');

        $this->assertSame(1, Asignacion::where('solicitud_id', $solicitud->id)->count());
    }

    public function test_no_se_asigna_una_solicitud_cerrada_o_cancelada(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $responsable = User::factory()->responsable()->create();

        foreach ([EstadoNombre::Cerrada, EstadoNombre::Cancelada] as $estado) {
            $solicitud = Solicitud::factory()->conEstado($estado)->create();

            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $responsable->id])
                ->assertStatus(422)
                ->assertJsonStructure(['message', 'errors']);

            $this->assertSame(0, $solicitud->asignaciones()->count());
        }
    }

    public function test_asignar_a_una_solicitud_en_proceso_no_cambia_su_estado(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => User::factory()->responsable()->create()->id])
            ->assertCreated();

        $this->assertSame(EstadoNombre::EnProceso->value, $solicitud->fresh()->estado->nombre);
    }

    public function test_asignar_deniega_a_estudiante_y_a_responsable_incluso_el_asignado(): void
    {
        $solicitud = Solicitud::factory()->create();
        $asignado = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $asignado->id]);
        $otro = User::factory()->responsable()->create();

        foreach ([$solicitud->estudiante, $asignado] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->postJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones", ['responsable_id' => $otro->id])->assertForbidden();
        }

        $this->assertTrue(Asignacion::where('responsable_id', $asignado->id)->first()->activo);
    }

    public function test_asignar_inexistente_404_y_sin_token_401(): void
    {
        $this->postJson('/api/v1/solicitudes/1/asignaciones', [])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->postJson('/api/v1/solicitudes/99999/asignaciones', ['responsable_id' => 1])->assertNotFound();
    }

    // --- GET /solicitudes/{id}/asignaciones ---

    public function test_historial_de_asignaciones_lo_ven_personal_admin_y_el_responsable_asignado(): void
    {
        $solicitud = Solicitud::factory()->create();
        $asignado = User::factory()->responsable()->create();
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id]);
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $asignado->id]);

        foreach ([User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create(), $asignado] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones")->assertOk()->assertJsonCount(2, 'data');
        }
    }

    public function test_historial_de_asignaciones_deniega_a_estudiante_otro_responsable_y_al_anterior(): void
    {
        $solicitud = Solicitud::factory()->create();
        $anterior = User::factory()->responsable()->create();
        $actual = User::factory()->responsable()->create();
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $anterior->id]);
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $actual->id]);

        foreach ([$solicitud->estudiante, User::factory()->responsable()->create(), $anterior] as $usuario) {
            Sanctum::actingAs($usuario);
            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones")->assertForbidden();
        }
    }

    public function test_historial_de_asignaciones_404_y_401(): void
    {
        $this->getJson('/api/v1/solicitudes/1/asignaciones')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->getJson('/api/v1/solicitudes/99999/asignaciones')->assertNotFound();
    }

    public function test_historial_de_asignaciones_no_hace_consultas_extra_por_fila(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $solicitud = Solicitud::factory()->create();

        $contar = function () use ($solicitud): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/v1/solicitudes/{$solicitud->id}/asignaciones")->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        Asignacion::factory()->count(2)->finalizada()->create(['solicitud_id' => $solicitud->id]);
        $contar(); // calienta cachés y conexiones
        $conDos = $contar();

        Asignacion::factory()->count(18)->finalizada()->create(['solicitud_id' => $solicitud->id]);
        $conVeinte = $contar();

        $this->assertSame($conDos, $conVeinte);
    }

    // --- Servicio, sin HTTP ---

    public function test_el_servicio_funciona_sin_peticion_http_y_lanza_validation_exception(): void
    {
        $solicitud = Solicitud::factory()->create();
        $admin = User::factory()->administrador()->create();
        $servicio = app(AsignacionService::class);

        $asignacion = $servicio->asignar($solicitud, User::factory()->responsable()->create(), $admin, null);
        $this->assertTrue($asignacion->activo);

        $this->expectException(ValidationException::class);
        $servicio->asignar($solicitud, User::factory()->estudiante()->create(), $admin, null);
    }
}
