<?php

namespace Tests\Feature\Panel;

use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PanelAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/panel/solicitudes')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/panel');
    }

    public function test_el_login_muestra_el_formulario(): void
    {
        $this->get('/login')->assertOk()->assertSee('Panel del personal');
    }

    public function test_el_personal_inicia_sesion_y_aterriza_segun_su_rol(): void
    {
        User::factory()->personalAdministrativo()->create(['email' => 'p@campus.test', 'password_hash' => 'clave12345']);
        User::factory()->responsable()->create(['email' => 'r@campus.test', 'password_hash' => 'clave12345']);

        $this->post('/login', ['email' => 'p@campus.test', 'password' => 'clave12345'])
            ->assertRedirect(route('panel.inicio'));
        $this->get('/panel')->assertRedirect(route('panel.solicitudes.index'));
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['email' => 'r@campus.test', 'password' => 'clave12345'])->assertRedirect();
        $this->get('/panel')->assertRedirect(route('panel.solicitudes.asignadas'));
    }

    public function test_un_estudiante_no_puede_iniciar_sesion_en_el_panel(): void
    {
        User::factory()->estudiante()->create(['email' => 'e@campus.test', 'password_hash' => 'clave12345']);

        $this->from('/login')->post('/login', ['email' => 'e@campus.test', 'password' => 'clave12345'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_no_puede_iniciar_sesion(): void
    {
        User::factory()->administrador()->inactivo()->create(['email' => 'a@campus.test', 'password_hash' => 'clave12345']);

        $this->post('/login', ['email' => 'a@campus.test', 'password' => 'clave12345'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_credenciales_incorrectas_no_inician_sesion(): void
    {
        User::factory()->administrador()->create(['email' => 'a@campus.test', 'password_hash' => 'clave12345']);

        $this->post('/login', ['email' => 'a@campus.test', 'password' => 'otra'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_estudiante_con_sesion_recibe_403_en_el_panel(): void
    {
        $estudiante = User::factory()->estudiante()->create();

        $this->actingAs($estudiante)->get('/panel/solicitudes')->assertForbidden();
        $this->actingAs($estudiante)->get('/panel/asignadas')->assertForbidden();
    }

    public function test_el_listado_global_es_solo_de_personal_y_administrador(): void
    {
        Solicitud::factory()->count(2)->create();

        $this->actingAs(User::factory()->personalAdministrativo()->create())->get('/panel/solicitudes')->assertOk();
        $this->actingAs(User::factory()->administrador()->create())->get('/panel/solicitudes')->assertOk();
        $this->actingAs(User::factory()->responsable()->create())->get('/panel/solicitudes')->assertForbidden();
    }

    public function test_el_responsable_solo_ve_sus_asignadas_y_no_las_ajenas(): void
    {
        $responsable = User::factory()->responsable()->create();
        $mia = Solicitud::factory()->create(['titulo' => 'Solicitud mía']);
        $ajena = Solicitud::factory()->create(['titulo' => 'Solicitud ajena']);
        Asignacion::factory()->create(['solicitud_id' => $mia->id, 'responsable_id' => $responsable->id]);
        Asignacion::factory()->create(['solicitud_id' => $ajena->id]);

        $this->actingAs($responsable)->get('/panel/asignadas')
            ->assertOk()
            ->assertSee('Solicitud mía')
            ->assertDontSee('Solicitud ajena');

        $this->actingAs($responsable)->get("/panel/solicitudes/{$mia->id}")->assertOk()->assertSee('Solicitud mía');
        $this->actingAs($responsable)->get("/panel/solicitudes/{$ajena->id}")->assertForbidden();
    }

    public function test_los_filtros_del_listado_se_aplican(): void
    {
        Solicitud::factory()->create(['titulo' => 'Proyector dañado']);
        Solicitud::factory()->create(['titulo' => 'Aire acondicionado']);

        $this->actingAs(User::factory()->administrador()->create())
            ->get('/panel/solicitudes?q=proyector')
            ->assertOk()
            ->assertSee('Proyector dañado')
            ->assertDontSee('Aire acondicionado');
    }

    public function test_los_filtros_invalidos_no_rompen_el_listado(): void
    {
        $this->actingAs(User::factory()->administrador()->create())
            ->get('/panel/solicitudes?per_page=999')
            ->assertSessionHasErrors('per_page');
    }

    public function test_el_listado_no_hace_consultas_por_fila(): void
    {
        $admin = User::factory()->administrador()->create();
        Solicitud::factory()->count(2)->create();

        // Primera petición de calentamiento: no debe contar el arranque de la aplicación.
        $this->actingAs($admin)->get('/panel/solicitudes')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/panel/solicitudes')->assertOk();
        $pocas = count(DB::getQueryLog());

        Solicitud::factory()->count(15)->create();

        DB::flushQueryLog();
        $this->actingAs($admin)->get('/panel/solicitudes')->assertOk();
        $muchas = count(DB::getQueryLog());

        $this->assertSame($pocas, $muchas, 'El listado hace consultas proporcionales al nº de filas (N+1).');
    }
}
