<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\HistorialEstado;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SolicitudDetalleTest extends TestCase
{
    use RefreshDatabase;

    private User $estudiante;

    private Solicitud $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
        $this->estudiante = User::factory()->estudiante()->create(['nombre' => 'Ana Pérez']);
        $this->solicitud = Solicitud::factory()->create(['estudiante_id' => $this->estudiante->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rutas(): array
    {
        return [
            'detalle' => ['/api/v1/solicitudes/%d'],
            'historial' => ['/api/v1/solicitudes/%d/historial-estados'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_sin_token_da_401(string $ruta): void
    {
        $this->getJson(sprintf($ruta, $this->solicitud->id))->assertStatus(401);
    }

    #[DataProvider('rutas')]
    public function test_el_dueno_accede(string $ruta): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson(sprintf($ruta, $this->solicitud->id))->assertOk();
    }

    #[DataProvider('rutas')]
    public function test_un_estudiante_no_alcanza_la_solicitud_de_otro(string $ruta): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->getJson(sprintf($ruta, $this->solicitud->id))
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
    }

    #[DataProvider('rutas')]
    public function test_un_responsable_sin_asignacion_activa_recibe_403(string $ruta): void
    {
        $responsable = User::factory()->responsable()->create();
        Sanctum::actingAs($responsable);

        $this->getJson(sprintf($ruta, $this->solicitud->id))->assertForbidden();

        // Una asignación ya cerrada tampoco da acceso.
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id, 'activo' => false]);
        $this->getJson(sprintf($ruta, $this->solicitud->id))->assertForbidden();
    }

    #[DataProvider('rutas')]
    public function test_un_responsable_con_asignacion_activa_accede(string $ruta): void
    {
        $responsable = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id, 'activo' => true]);
        Sanctum::actingAs($responsable);

        $this->getJson(sprintf($ruta, $this->solicitud->id))->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rolesConAccesoTotal(): array
    {
        return [
            'personal' => ['personalAdministrativo'],
            'administrador' => ['administrador'],
        ];
    }

    #[DataProvider('rolesConAccesoTotal')]
    public function test_personal_y_administrador_ven_cualquiera(string $rol): void
    {
        Sanctum::actingAs(User::factory()->{$rol}()->create());

        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}")->assertOk();
        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}/historial-estados")->assertOk();
    }

    #[DataProvider('rutas')]
    public function test_solicitud_inexistente_da_404(string $ruta): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->getJson(sprintf($ruta, 999999))
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_403_si_existe_y_404_si_no(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}")->assertForbidden();
        $this->getJson('/api/v1/solicitudes/999999')->assertNotFound();
    }

    public function test_el_detalle_tiene_la_forma_del_contrato_con_estudiante_y_responsable(): void
    {
        $responsable = User::factory()->responsable()->create(['nombre' => 'Rosa Gómez']);
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id, 'activo' => true]);
        Sanctum::actingAs($this->estudiante);

        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->solicitud->id)
            ->assertJsonPath('data.estudiante', ['id' => $this->estudiante->id, 'nombre' => 'Ana Pérez'])
            ->assertJsonPath('data.responsable', ['id' => $responsable->id, 'nombre' => 'Rosa Gómez'])
            ->assertJsonStructure(['data' => [
                'id', 'titulo', 'descripcion', 'ubicacion', 'tipo' => ['id', 'nombre', 'descripcion'],
                'prioridad' => ['id', 'nombre', 'nivel'], 'estado' => ['id', 'nombre'],
                'estudiante' => ['id', 'nombre'], 'responsable' => ['id', 'nombre'], 'created_at', 'updated_at',
            ]]);
    }

    public function test_el_detalle_sin_asignacion_trae_responsable_null(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}")->assertJsonPath('data.responsable', null);
    }

    public function test_un_id_no_numerico_da_404(): void
    {
        Sanctum::actingAs($this->estudiante);

        $this->getJson('/api/v1/solicitudes/abc')->assertNotFound();
    }

    public function test_el_historial_va_del_mas_antiguo_al_mas_reciente_con_la_forma_del_contrato(): void
    {
        $admin = User::factory()->administrador()->create(['nombre' => 'Admin Demo']);
        $this->solicitud->cambiarEstado(EstadoNombre::Asignada, $admin);
        $this->solicitud->cambiarEstado(EstadoNombre::EnProceso, $admin, 'Iniciamos la revisión');
        // Mismo segundo para todas: el desempate por id debe conservar el orden cronológico.
        DB::table('historial_estados')->where('solicitud_id', $this->solicitud->id)->update(['created_at' => '2026-09-24 20:37:08']);
        Sanctum::actingAs($this->estudiante);

        $respuesta = $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}/historial-estados")->assertOk();

        $this->assertSame(['data'], array_keys($respuesta->json()));
        $this->assertSame(['pendiente', 'asignada', 'en_proceso'], $respuesta->json('data.*.estado.nombre'));
        $respuesta
            ->assertJsonPath('data.0.comentario', 'Solicitud registrada')
            ->assertJsonPath('data.0.autor', ['id' => $this->estudiante->id, 'nombre' => 'Ana Pérez'])
            ->assertJsonPath('data.1.comentario', null)
            ->assertJsonPath('data.1.autor.nombre', 'Admin Demo')
            ->assertJsonPath('data.2.comentario', 'Iniciamos la revisión')
            ->assertJsonPath('data.0.created_at', '2026-09-24T20:37:08+00:00');
        $this->assertSame(
            ['id', 'estado', 'comentario', 'autor', 'created_at'],
            array_keys($respuesta->json('data.0')),
        );
    }

    public function test_el_historial_solo_incluye_los_de_esa_solicitud(): void
    {
        Solicitud::factory()->count(2)->create();
        Sanctum::actingAs($this->estudiante);

        $this->assertSame(1, HistorialEstado::where('solicitud_id', $this->solicitud->id)->count());
        $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}/historial-estados")->assertJsonCount(1, 'data');
    }

    public function test_el_historial_no_hace_consultas_por_fila(): void
    {
        $admin = User::factory()->administrador()->create();
        Sanctum::actingAs($this->estudiante);

        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/v1/solicitudes/{$this->solicitud->id}/historial-estados")->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->solicitud->cambiarEstado(EstadoNombre::Asignada, $admin);
        $contar(); // calentamiento: la primera petición actualiza last_used_at del token
        $conDos = $contar();
        $this->solicitud->cambiarEstado(EstadoNombre::EnProceso, $admin);
        $this->solicitud->cambiarEstado(EstadoNombre::Cerrada, $admin);
        $this->assertSame($conDos, $contar());
    }
}
