<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Enums\TipoNombre;
use App\Models\Asignacion;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosSeeder::class);
        $this->admin = User::factory()->administrador()->create();
    }

    /**
     * Solicitud cerrada con el tiempo de atención exacto: creada en $creada y
     * cerrada $horas horas después (la fila de historial se ajusta a mano).
     */
    private function cerrada(Carbon $creada, float $horas, TipoNombre $tipo = TipoNombre::Mantenimiento, PrioridadNombre $prioridad = PrioridadNombre::Media): Solicitud
    {
        $solicitud = Solicitud::factory()
            ->conEstado(EstadoNombre::EnProceso)->conTipo($tipo)->conPrioridad($prioridad)
            ->create(['created_at' => $creada]);

        $solicitud->cambiarEstado(EstadoNombre::Cerrada, $this->admin);

        DB::table('historial_estados')
            ->where('solicitud_id', $solicitud->id)
            ->where('estado_id', EstadoSolicitud::idDe(EstadoNombre::Cerrada))
            ->update(['created_at' => $creada->copy()->addSeconds((int) round($horas * 3600))]);

        return $solicitud;
    }

    private function conAsignacion(Solicitud $solicitud): Solicitud
    {
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);

        return $solicitud;
    }

    /**
     * Datos conocidos: 6 solicitudes con estados, tipos, prioridades y tiempos controlados.
     *
     * @return array<string, Solicitud>
     */
    private function escenario(): array
    {
        $a = $this->cerrada(now()->subDays(10), 10, TipoNombre::Mantenimiento, PrioridadNombre::Baja);
        // Un cierre anterior extra en el historial: cuenta solo el ÚLTIMO paso a cerrada.
        DB::table('historial_estados')->insert([
            'solicitud_id' => $a->id,
            'estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cerrada),
            'cambiado_por' => $this->admin->id,
            'created_at' => now()->subDays(10)->addHour(),
        ]);
        $a->refresh();

        return [
            'a' => $a,
            'b' => $this->cerrada(now()->subDays(60), 2.5, TipoNombre::Mantenimiento, PrioridadNombre::Alta),
            'c' => Solicitud::factory()->conTipo(TipoNombre::SoporteTecnologico)->create(),
            'd' => $this->conAsignacion(Solicitud::factory()->conTipo(TipoNombre::SoporteTecnologico)->conPrioridad(PrioridadNombre::Urgente)->create()),
            'e' => $this->conAsignacion(Solicitud::factory()->conTipo(TipoNombre::Infraestructura)->conEstado(EstadoNombre::EnProceso)->create()),
            'f' => Solicitud::factory()->conTipo(TipoNombre::Infraestructura)->conEstado(EstadoNombre::Cancelada)->create(),
        ];
    }

    // ---- Resumen ---------------------------------------------------------

    public function test_resumen_calcula_cada_cifra_con_datos_conocidos(): void
    {
        $this->escenario();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/dashboard/resumen')
            ->assertOk()
            ->assertExactJson(['data' => [
                'total' => 6,
                'sin_asignar' => 1,
                'por_estado' => ['pendiente' => 2, 'asignada' => 0, 'en_proceso' => 1, 'cerrada' => 2, 'cancelada' => 1],
                'por_prioridad' => ['baja' => 1, 'media' => 3, 'alta' => 1, 'urgente' => 1],
                'por_tipo' => ['mantenimiento' => 2, 'soporte_tecnologico' => 2, 'infraestructura' => 2, 'equipamiento' => 0, 'otros' => 0],
                'cerradas_ultimos_30_dias' => 1,
                'tiempo_promedio_atencion_horas' => 6.25,
            ]]);
    }

    public function test_resumen_sin_solicitudes_trae_todas_las_claves_en_cero_y_promedio_nulo(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/dashboard/resumen')
            ->assertOk()
            ->assertExactJson(['data' => [
                'total' => 0,
                'sin_asignar' => 0,
                'por_estado' => ['pendiente' => 0, 'asignada' => 0, 'en_proceso' => 0, 'cerrada' => 0, 'cancelada' => 0],
                'por_prioridad' => ['baja' => 0, 'media' => 0, 'alta' => 0, 'urgente' => 0],
                'por_tipo' => ['mantenimiento' => 0, 'soporte_tecnologico' => 0, 'infraestructura' => 0, 'equipamiento' => 0, 'otros' => 0],
                'cerradas_ultimos_30_dias' => 0,
                'tiempo_promedio_atencion_horas' => null,
            ]]);
    }

    public function test_sin_asignar_ignora_las_pendientes_con_asignacion_finalizada_como_asignadas(): void
    {
        // Una asignación finalizada (inactiva) no cuenta: sigue sin asignar.
        $solicitud = Solicitud::factory()->create();
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id]);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/dashboard/resumen')->assertJsonPath('data.sin_asignar', 1);
    }

    // ---- Reporte consolidado --------------------------------------------

    public function test_reporte_de_solicitudes_incluye_cerrada_at_y_la_forma_de_solicitud(): void
    {
        $datos = $this->escenario();
        Sanctum::actingAs($this->admin);

        $respuesta = $this->getJson('/api/v1/reportes/solicitudes?per_page=50&orden=created_at')
            ->assertOk()
            ->assertJsonPath('meta.total', 6)
            ->assertJsonStructure(['data' => [['id', 'titulo', 'tipo', 'prioridad', 'estado', 'estudiante', 'responsable', 'created_at', 'cerrada_at']], 'links', 'meta']);

        $porId = collect($respuesta->json('data'))->keyBy('id');

        // Cierre = último paso a cerrada: creada + 10 h, no la fila anterior de +1 h.
        $this->assertSame(
            $datos['a']->created_at->copy()->addHours(10)->utc()->toIso8601String(),
            $porId[$datos['a']->id]['cerrada_at'],
        );
        $this->assertNull($porId[$datos['c']->id]['cerrada_at']);
        $this->assertNotNull($porId[$datos['d']->id]['responsable']);
        $this->assertNull($porId[$datos['c']->id]['responsable']);
    }

    public function test_reporte_de_solicitudes_filtra_por_estado_tipo_prioridad_y_fechas(): void
    {
        $datos = $this->escenario();
        Sanctum::actingAs($this->admin);

        $cerrada = EstadoSolicitud::idDe(EstadoNombre::Cerrada);

        $this->getJson("/api/v1/reportes/solicitudes?estado_id={$cerrada}")->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/reportes/solicitudes?tipo_id='.$datos['e']->tipo_id)->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/reportes/solicitudes?prioridad_id='.$datos['d']->prioridad_id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/reportes/solicitudes?desde='.now()->subDays(30)->toDateString())->assertJsonPath('meta.total', 5);
    }

    public function test_reporte_de_solicitudes_pagina(): void
    {
        Solicitud::factory()->count(4)->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reportes/solicitudes?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/reportes/solicitudes?per_page=51')->assertUnprocessable();
    }

    // ---- Por tipo --------------------------------------------------------

    public function test_solicitudes_por_tipo_desglosa_por_estado_e_incluye_tipos_sin_solicitudes(): void
    {
        $datos = $this->escenario();
        Sanctum::actingAs($this->admin);

        $respuesta = $this->getJson('/api/v1/reportes/solicitudes-por-tipo')->assertOk();
        $filas = $respuesta->json('data');

        $this->assertSame(
            ['mantenimiento', 'soporte_tecnologico', 'infraestructura', 'equipamiento', 'otros'],
            array_column($filas, 'tipo'),
        );
        $this->assertSame($datos['a']->tipo_id, $filas[0]['tipo_id']);
        $this->assertSame(2, $filas[0]['total']);
        $this->assertSame(['pendiente' => 0, 'asignada' => 0, 'en_proceso' => 0, 'cerrada' => 2, 'cancelada' => 0], $filas[0]['por_estado']);
        $this->assertSame(['pendiente' => 2, 'asignada' => 0, 'en_proceso' => 0, 'cerrada' => 0, 'cancelada' => 0], $filas[1]['por_estado']);
        $this->assertSame(['pendiente' => 0, 'asignada' => 0, 'en_proceso' => 1, 'cerrada' => 0, 'cancelada' => 1], $filas[2]['por_estado']);
        // Tipos sin solicitudes: total 0 y todas las claves de estado en 0.
        foreach ([3, 4] as $i) {
            $this->assertSame(0, $filas[$i]['total']);
            $this->assertSame(['pendiente' => 0, 'asignada' => 0, 'en_proceso' => 0, 'cerrada' => 0, 'cancelada' => 0], $filas[$i]['por_estado']);
        }
    }

    public function test_solicitudes_por_tipo_respeta_el_rango_de_fechas(): void
    {
        $this->escenario();
        Sanctum::actingAs($this->admin);

        $filas = $this->getJson('/api/v1/reportes/solicitudes-por-tipo?desde='.now()->subDays(30)->toDateString())
            ->assertOk()->json('data');

        $this->assertSame(1, $filas[0]['total']);
    }

    // ---- Tiempos de atención ---------------------------------------------

    public function test_tiempos_de_atencion_calcula_promedio_minimo_y_maximo_por_tipo(): void
    {
        $this->cerrada(now()->subDays(10), 10, TipoNombre::Mantenimiento);
        $this->cerrada(now()->subDays(60), 2.5, TipoNombre::Mantenimiento);
        $this->cerrada(now()->subDays(5), 1.25, TipoNombre::Otros);
        // No cerradas: no cuentan.
        Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
        Sanctum::actingAs($this->admin);

        $respuesta = $this->getJson('/api/v1/reportes/tiempos-atencion')->assertOk();

        $this->assertSame(3, $respuesta->json('data.total_cerradas'));
        $this->assertSame(4.58, $respuesta->json('data.promedio_horas')); // (10 + 2.5 + 1.25) / 3
        $this->assertSame(1.25, $respuesta->json('data.minimo_horas'));
        $this->assertEquals(10.0, $respuesta->json('data.maximo_horas'));

        $porTipo = collect($respuesta->json('data.por_tipo'))->keyBy('tipo');
        $this->assertCount(5, $porTipo);
        $this->assertSame(2, $porTipo['mantenimiento']['total_cerradas']);
        $this->assertSame(6.25, $porTipo['mantenimiento']['promedio_horas']);
        $this->assertSame(1.25, $porTipo['otros']['promedio_horas']);
        $this->assertSame(0, $porTipo['infraestructura']['total_cerradas']);
        $this->assertNull($porTipo['infraestructura']['promedio_horas']);
    }

    public function test_tiempos_de_atencion_sin_cerradas_devuelve_nulos(): void
    {
        Solicitud::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reportes/tiempos-atencion')
            ->assertOk()
            ->assertJsonPath('data.total_cerradas', 0)
            ->assertJsonPath('data.promedio_horas', null)
            ->assertJsonPath('data.minimo_horas', null)
            ->assertJsonPath('data.maximo_horas', null)
            ->assertJsonCount(5, 'data.por_tipo')
            ->assertJsonPath('data.por_tipo.0.promedio_horas', null);
    }

    public function test_tiempos_de_atencion_filtra_por_fecha_de_creacion(): void
    {
        $this->cerrada(now()->subDays(10), 10);
        $this->cerrada(now()->subDays(60), 2.5);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/reportes/tiempos-atencion?desde='.now()->subDays(30)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.total_cerradas', 1)
            ->assertJsonPath('data.promedio_horas', 10);
    }

    // ---- Autorización y validación ---------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function endpoints(): array
    {
        return [
            'resumen' => ['/api/v1/dashboard/resumen'],
            'reporte de solicitudes' => ['/api/v1/reportes/solicitudes'],
            'solicitudes por tipo' => ['/api/v1/reportes/solicitudes-por-tipo'],
            'tiempos de atencion' => ['/api/v1/reportes/tiempos-atencion'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_los_roles_sin_permiso_reciben_403(string $url): void
    {
        foreach (['estudiante', 'personalAdministrativo', 'responsable'] as $rol) {
            Sanctum::actingAs(User::factory()->{$rol}()->create());

            $this->getJson($url)
                ->assertForbidden()
                ->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
        }
    }

    #[DataProvider('endpoints')]
    public function test_sin_token_responde_401(string $url): void
    {
        $this->getJson($url)->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_el_administrador_accede(string $url): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson($url)->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpointsConFechas(): array
    {
        return array_slice(self::endpoints(), 1);
    }

    #[DataProvider('endpointsConFechas')]
    public function test_desde_mayor_que_hasta_o_formato_invalido_da_422(string $url): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson($url.'?desde=2026-05-10&hasta=2026-05-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson($url.'?desde=10/05/2026')->assertUnprocessable()->assertJsonValidationErrors('desde');
        $this->getJson($url.'?hasta=ayer')->assertUnprocessable()->assertJsonValidationErrors('hasta');
    }

    public function test_un_rol_sin_permiso_recibe_403_antes_que_422(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->getJson('/api/v1/reportes/tiempos-atencion?desde=basura')->assertForbidden();
    }

    // ---- Sin N+1 ---------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function endpointsDeConsulta(): array
    {
        return [
            'resumen' => ['/api/v1/dashboard/resumen'],
            'reporte de solicitudes' => ['/api/v1/reportes/solicitudes?per_page=50'],
            'solicitudes por tipo' => ['/api/v1/reportes/solicitudes-por-tipo'],
            'tiempos de atencion' => ['/api/v1/reportes/tiempos-atencion'],
        ];
    }

    private function crearSolicitudesCompletas(int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $solicitud = $i % 2 === 0
                ? $this->cerrada(now()->subDays($i + 1), 3 + $i)
                : Solicitud::factory()->conTipo(TipoNombre::Otros)->create();

            $this->conAsignacion($solicitud);
        }
    }

    private function contarConsultas(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($url)->assertOk();
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $consultas;
    }

    #[DataProvider('endpointsDeConsulta')]
    public function test_el_numero_de_consultas_no_depende_del_numero_de_solicitudes(string $url): void
    {
        Sanctum::actingAs($this->admin);

        $this->crearSolicitudesCompletas(5);
        $this->getJson($url)->assertOk(); // calentamiento: descarta consultas de un solo uso
        $con5 = $this->contarConsultas($url);

        $this->crearSolicitudesCompletas(55);
        $con60 = $this->contarConsultas($url);

        $this->assertSame(60, Solicitud::count());
        $this->assertSame($con5, $con60, "Consultas con 5: {$con5}; con 60: {$con60}");
    }
}
