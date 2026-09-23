<?php

namespace Tests\Feature\Policies;

use App\Models\Adjunto;
use App\Models\Asignacion;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Matriz de autorización. Cada regla se prueba en las dos direcciones:
 * que se permite a quien corresponde y que se DENIEGA a quien no.
 */
class SolicitudPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Solicitud $solicitud;

    private User $dueno;

    private User $otroEstudiante;

    private User $personal;

    private User $admin;

    private User $responsableAsignado;

    private User $otroResponsable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dueno = User::factory()->estudiante()->create();
        $this->otroEstudiante = User::factory()->estudiante()->create();
        $this->personal = User::factory()->personalAdministrativo()->create();
        $this->admin = User::factory()->administrador()->create();
        $this->responsableAsignado = User::factory()->responsable()->create();
        $this->otroResponsable = User::factory()->responsable()->create();

        $this->solicitud = Solicitud::factory()->create(['estudiante_id' => $this->dueno->id]);
        Asignacion::factory()->create([
            'solicitud_id' => $this->solicitud->id,
            'responsable_id' => $this->responsableAsignado->id,
            'asignado_por' => $this->admin->id,
        ]);
    }

    public function test_view(): void
    {
        $this->assertAllowed($this->dueno, 'view');
        $this->assertAllowed($this->personal, 'view');
        $this->assertAllowed($this->admin, 'view');
        $this->assertAllowed($this->responsableAsignado, 'view');

        $this->assertDenied($this->otroEstudiante, 'view');
        $this->assertDenied($this->otroResponsable, 'view');
    }

    public function test_un_responsable_con_asignacion_finalizada_pierde_el_acceso(): void
    {
        Asignacion::where('solicitud_id', $this->solicitud->id)->update(['activo' => false, 'fecha_fin' => now()]);

        $this->assertDenied($this->responsableAsignado, 'view');
        $this->assertDenied($this->responsableAsignado, 'changeState');
        $this->assertDenied($this->responsableAsignado, 'createAction');
    }

    public function test_view_any_y_view_assigned(): void
    {
        $this->assertTrue(Gate::forUser($this->personal)->allows('viewAny', Solicitud::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('viewAny', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->dueno)->allows('viewAny', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->responsableAsignado)->allows('viewAny', Solicitud::class));

        $this->assertTrue(Gate::forUser($this->responsableAsignado)->allows('viewAssigned', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('viewAssigned', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->dueno)->allows('viewAssigned', Solicitud::class));
    }

    public function test_create_solo_estudiante(): void
    {
        $this->assertTrue(Gate::forUser($this->dueno)->allows('create', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->personal)->allows('create', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', Solicitud::class));
        $this->assertFalse(Gate::forUser($this->responsableAsignado)->allows('create', Solicitud::class));
    }

    public function test_classify_y_assign_solo_personal_y_administrador(): void
    {
        foreach (['classify', 'assign'] as $habilidad) {
            $this->assertAllowed($this->personal, $habilidad);
            $this->assertAllowed($this->admin, $habilidad);

            $this->assertDenied($this->dueno, $habilidad);
            $this->assertDenied($this->responsableAsignado, $habilidad);
            $this->assertDenied($this->otroResponsable, $habilidad);
        }
    }

    public function test_change_state_view_assignments_y_view_actions(): void
    {
        foreach (['changeState', 'viewAssignments', 'viewActions'] as $habilidad) {
            $this->assertAllowed($this->personal, $habilidad);
            $this->assertAllowed($this->admin, $habilidad);
            $this->assertAllowed($this->responsableAsignado, $habilidad);

            $this->assertDenied($this->dueno, $habilidad);
            $this->assertDenied($this->otroEstudiante, $habilidad);
            $this->assertDenied($this->otroResponsable, $habilidad);
        }
    }

    public function test_create_action_solo_el_responsable_asignado(): void
    {
        $this->assertAllowed($this->responsableAsignado, 'createAction');

        $this->assertDenied($this->otroResponsable, 'createAction');
        $this->assertDenied($this->admin, 'createAction');
        $this->assertDenied($this->personal, 'createAction');
        $this->assertDenied($this->dueno, 'createAction');
    }

    public function test_upload_attachment_solo_el_estudiante_dueno(): void
    {
        $this->assertAllowed($this->dueno, 'uploadAttachment');

        $this->assertDenied($this->otroEstudiante, 'uploadAttachment');
        $this->assertDenied($this->admin, 'uploadAttachment');
        $this->assertDenied($this->responsableAsignado, 'uploadAttachment');
    }

    public function test_un_adjunto_se_ve_solo_si_se_ve_su_solicitud(): void
    {
        $adjunto = Adjunto::factory()->create(['solicitud_id' => $this->solicitud->id, 'subido_por' => $this->dueno->id]);

        $this->assertTrue(Gate::forUser($this->dueno)->allows('view', $adjunto));
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $adjunto));
        $this->assertTrue(Gate::forUser($this->responsableAsignado)->allows('view', $adjunto));

        $this->assertFalse(Gate::forUser($this->otroEstudiante)->allows('view', $adjunto));
        $this->assertFalse(Gate::forUser($this->otroResponsable)->allows('view', $adjunto));
    }

    public function test_una_notificacion_solo_la_marca_su_dueno(): void
    {
        $notificacion = Notificacion::factory()->create([
            'usuario_id' => $this->dueno->id,
            'solicitud_id' => $this->solicitud->id,
        ]);

        $this->assertTrue(Gate::forUser($this->dueno)->allows('markAsRead', $notificacion));
        $this->assertFalse(Gate::forUser($this->otroEstudiante)->allows('markAsRead', $notificacion));
        $this->assertFalse(Gate::forUser($this->admin)->allows('markAsRead', $notificacion));
    }

    public function test_solo_el_administrador_ve_reportes_y_solo_gestion_lista_responsables(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('ver-reportes'));
        $this->assertFalse(Gate::forUser($this->personal)->allows('ver-reportes'));
        $this->assertFalse(Gate::forUser($this->responsableAsignado)->allows('ver-reportes'));
        $this->assertFalse(Gate::forUser($this->dueno)->allows('ver-reportes'));

        $this->assertTrue(Gate::forUser($this->admin)->allows('listar-responsables'));
        $this->assertTrue(Gate::forUser($this->personal)->allows('listar-responsables'));
        $this->assertFalse(Gate::forUser($this->responsableAsignado)->allows('listar-responsables'));
        $this->assertFalse(Gate::forUser($this->dueno)->allows('listar-responsables'));
    }

    private function assertAllowed(User $usuario, string $habilidad): void
    {
        $this->assertTrue(
            Gate::forUser($usuario)->allows($habilidad, $this->solicitud),
            "{$habilidad} debería permitirse a {$usuario->rol->nombre}",
        );
    }

    private function assertDenied(User $usuario, string $habilidad): void
    {
        $this->assertFalse(
            Gate::forUser($usuario)->allows($habilidad, $this->solicitud),
            "{$habilidad} debería denegarse a {$usuario->rol->nombre}",
        );
    }
}
