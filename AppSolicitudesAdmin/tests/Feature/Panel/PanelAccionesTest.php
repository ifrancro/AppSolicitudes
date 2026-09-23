<?php

namespace Tests\Feature\Panel;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Models\Adjunto;
use App\Models\Asignacion;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanelAccionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $personal;

    private User $responsable;

    private Solicitud $solicitud;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosSeeder::class);

        $this->admin = User::factory()->administrador()->create();
        $this->personal = User::factory()->personalAdministrativo()->create();
        $this->responsable = User::factory()->responsable()->create();
        $this->solicitud = Solicitud::factory()->create();
    }

    private function idEstado(EstadoNombre $estado): int
    {
        return EstadoSolicitud::idDe($estado);
    }

    private function asignarA(User $responsable): void
    {
        Asignacion::factory()->create(['solicitud_id' => $this->solicitud->id, 'responsable_id' => $responsable->id]);
        $this->solicitud->cambiarEstado(EstadoNombre::Asignada, $this->admin);
    }

    public function test_el_personal_clasifica_una_solicitud(): void
    {
        $urgente = Prioridad::where('nombre', PrioridadNombre::Urgente->value)->value('id');

        $this->actingAs($this->personal)
            ->post("/panel/solicitudes/{$this->solicitud->id}/clasificacion", ['prioridad_id' => $urgente])
            ->assertRedirect(route('panel.solicitudes.show', $this->solicitud))
            ->assertSessionHas('exito');

        $this->assertSame($urgente, $this->solicitud->fresh()->prioridad_id);
    }

    public function test_responsable_y_estudiante_no_pueden_clasificar(): void
    {
        $urgente = Prioridad::where('nombre', PrioridadNombre::Urgente->value)->value('id');
        $url = "/panel/solicitudes/{$this->solicitud->id}/clasificacion";

        $this->actingAs($this->responsable)->post($url, ['prioridad_id' => $urgente])->assertForbidden();
        $this->actingAs(User::factory()->estudiante()->create())->post($url, ['prioridad_id' => $urgente])->assertForbidden();

        $this->assertNotSame($urgente, $this->solicitud->fresh()->prioridad_id);
    }

    public function test_asignar_crea_la_asignacion_y_pasa_a_asignada(): void
    {
        $this->actingAs($this->admin)
            ->post("/panel/solicitudes/{$this->solicitud->id}/asignacion", ['responsable_id' => $this->responsable->id])
            ->assertRedirect()
            ->assertSessionHas('exito');

        $this->assertSame($this->responsable->id, $this->solicitud->asignacionActiva()->value('responsable_id'));
        $this->assertSame(EstadoNombre::Asignada->value, $this->solicitud->fresh()->estado->nombre);
    }

    public function test_reasignar_conserva_el_historial(): void
    {
        $otro = User::factory()->responsable()->create();
        $this->actingAs($this->admin)
            ->post("/panel/solicitudes/{$this->solicitud->id}/asignacion", ['responsable_id' => $this->responsable->id]);
        $this->actingAs($this->admin)
            ->post("/panel/solicitudes/{$this->solicitud->id}/asignacion", ['responsable_id' => $otro->id]);

        $this->assertSame(2, $this->solicitud->asignaciones()->count());
        $anterior = $this->solicitud->asignaciones()->where('responsable_id', $this->responsable->id)->first();
        $this->assertFalse($anterior->activo);
        $this->assertNotNull($anterior->fecha_fin);
    }

    public function test_no_se_puede_asignar_a_quien_no_es_responsable(): void
    {
        $this->actingAs($this->admin)
            ->post("/panel/solicitudes/{$this->solicitud->id}/asignacion", ['responsable_id' => $this->personal->id])
            ->assertSessionHasErrors('responsable_id');

        $this->assertSame(0, $this->solicitud->asignaciones()->count());
    }

    public function test_responsable_y_estudiante_no_pueden_asignar(): void
    {
        $url = "/panel/solicitudes/{$this->solicitud->id}/asignacion";

        $this->actingAs($this->responsable)->post($url, ['responsable_id' => $this->responsable->id])->assertForbidden();
        $this->actingAs(User::factory()->estudiante()->create())->post($url, ['responsable_id' => $this->responsable->id])->assertForbidden();

        $this->assertSame(0, $this->solicitud->asignaciones()->count());
    }

    public function test_el_responsable_avanza_el_estado_y_queda_en_la_bitacora(): void
    {
        $this->asignarA($this->responsable);
        $url = "/panel/solicitudes/{$this->solicitud->id}/estado";

        $this->actingAs($this->responsable)
            ->post($url, ['estado_id' => $this->idEstado(EstadoNombre::EnProceso), 'comentario' => 'Empiezo'])
            ->assertSessionHas('exito');
        $this->actingAs($this->responsable)
            ->post($url, ['estado_id' => $this->idEstado(EstadoNombre::Cerrada)])
            ->assertSessionHas('exito');

        $this->assertSame(EstadoNombre::Cerrada->value, $this->solicitud->fresh()->estado->nombre);
        $ultimoConComentario = $this->solicitud->historialEstados()->where('comentario', 'Empiezo')->first();
        $this->assertSame($this->responsable->id, $ultimoConComentario->cambiado_por);
    }

    public function test_el_responsable_no_puede_cancelar_ni_saltarse_transiciones(): void
    {
        $this->asignarA($this->responsable);
        $url = "/panel/solicitudes/{$this->solicitud->id}/estado";

        $this->actingAs($this->responsable)
            ->post($url, ['estado_id' => $this->idEstado(EstadoNombre::Cancelada), 'comentario' => 'x'])
            ->assertForbidden();

        // asignada -> cerrada es una transición ilegal
        $this->actingAs($this->responsable)
            ->post($url, ['estado_id' => $this->idEstado(EstadoNombre::Cerrada)])
            ->assertSessionHasErrors('estado_id');

        $this->assertSame(EstadoNombre::Asignada->value, $this->solicitud->fresh()->estado->nombre);
    }

    public function test_cancelar_exige_comentario(): void
    {
        $this->actingAs($this->admin)
            ->post("/panel/solicitudes/{$this->solicitud->id}/estado", ['estado_id' => $this->idEstado(EstadoNombre::Cancelada)])
            ->assertSessionHasErrors('comentario');
    }

    public function test_un_responsable_ajeno_no_puede_cambiar_el_estado(): void
    {
        $this->asignarA($this->responsable);
        $intruso = User::factory()->responsable()->create();

        $this->actingAs($intruso)
            ->post("/panel/solicitudes/{$this->solicitud->id}/estado", ['estado_id' => $this->idEstado(EstadoNombre::EnProceso)])
            ->assertForbidden();
    }

    public function test_el_responsable_registra_acciones_y_el_administrador_no(): void
    {
        $this->asignarA($this->responsable);
        $url = "/panel/solicitudes/{$this->solicitud->id}/acciones";

        $this->actingAs($this->admin)->post($url, ['descripcion' => 'Yo no debería'])->assertForbidden();
        $this->actingAs($this->responsable)->post($url, ['descripcion' => 'Cambié la lámpara'])->assertSessionHas('exito');

        $this->assertSame(['Cambié la lámpara'], $this->solicitud->acciones()->pluck('descripcion')->all());
    }

    public function test_el_detalle_solo_ofrece_los_formularios_que_el_rol_puede_usar(): void
    {
        $this->asignarA($this->responsable);
        $url = "/panel/solicitudes/{$this->solicitud->id}";

        $this->actingAs($this->personal)->get($url)->assertOk()
            ->assertSee('Clasificar')->assertSee('responsable')->assertDontSee('Registrar acción realizada');

        $this->actingAs($this->responsable)->get($url)->assertOk()
            ->assertSee('Registrar acción realizada')->assertDontSee('Clasificar')->assertDontSee('>Asignar<', false);
    }

    public function test_descarga_de_evidencias_autorizada(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('adjuntos/prueba.jpg', 'contenido');
        $this->asignarA($this->responsable);
        $adjunto = Adjunto::factory()->create([
            'solicitud_id' => $this->solicitud->id,
            'url_archivo' => 'adjuntos/prueba.jpg',
            'subido_por' => $this->solicitud->estudiante_id,
        ]);

        $this->actingAs($this->responsable)->get("/panel/adjuntos/{$adjunto->id}")->assertOk();
        $this->actingAs($this->admin)->get("/panel/adjuntos/{$adjunto->id}")->assertOk();

        $this->actingAs(User::factory()->responsable()->create())->get("/panel/adjuntos/{$adjunto->id}")->assertForbidden();
        $this->actingAs($this->solicitud->estudiante)->get("/panel/adjuntos/{$adjunto->id}")->assertForbidden();
    }

    public function test_el_dashboard_es_solo_del_administrador(): void
    {
        $this->actingAs($this->admin)->get('/panel/dashboard')->assertOk()->assertSee('Solicitudes por tipo');

        $this->actingAs($this->personal)->get('/panel/dashboard')->assertForbidden();
        $this->actingAs($this->responsable)->get('/panel/dashboard')->assertForbidden();
    }

    public function test_el_dashboard_valida_el_rango_de_fechas(): void
    {
        $this->actingAs($this->admin)
            ->get('/panel/dashboard?desde=2026-05-10&hasta=2026-05-01')
            ->assertSessionHasErrors('hasta');
    }

    public function test_el_administrador_aterriza_en_el_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/panel')->assertRedirect(route('panel.dashboard'));
    }
}
