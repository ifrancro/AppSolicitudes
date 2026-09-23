<?php

namespace Tests\Feature\Api;

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->estudiante()->create();
    }

    public function test_lista_solo_las_propias_mas_recientes_primero(): void
    {
        $vieja = Notificacion::factory()->create(['usuario_id' => $this->usuario->id, 'created_at' => now()->subDay()]);
        $nueva = Notificacion::factory()->create(['usuario_id' => $this->usuario->id, 'created_at' => now()]);
        $ajena = Notificacion::factory()->create();
        Sanctum::actingAs($this->usuario);

        $r = $this->getJson('/api/v1/notificaciones')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'solicitud_id', 'mensaje', 'leido', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total', 'no_leidas'],
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $nueva->id)
            ->assertJsonPath('data.1.id', $vieja->id)
            ->assertJsonPath('data.0.leido', false);

        $this->assertNotContains($ajena->id, collect($r->json('data'))->pluck('id')->all());
    }

    public function test_no_leidas_es_independiente_del_filtro_y_de_la_pagina(): void
    {
        Notificacion::factory()->count(3)->create(['usuario_id' => $this->usuario->id]);
        Notificacion::factory()->count(2)->leida()->create(['usuario_id' => $this->usuario->id]);
        Notificacion::factory()->count(4)->create();
        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/v1/notificaciones')->assertJsonPath('meta.total', 5)->assertJsonPath('meta.no_leidas', 3);
        $this->getJson('/api/v1/notificaciones?leido=1')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.no_leidas', 3);
        $this->getJson('/api/v1/notificaciones?leido=0')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.no_leidas', 3);
        $this->getJson('/api/v1/notificaciones?per_page=1&page=4')->assertJsonPath('meta.no_leidas', 3);
    }

    public function test_filtro_leido(): void
    {
        $sinLeer = Notificacion::factory()->create(['usuario_id' => $this->usuario->id]);
        $leida = Notificacion::factory()->leida()->create(['usuario_id' => $this->usuario->id]);
        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/v1/notificaciones?leido=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sinLeer->id);
        $this->getJson('/api/v1/notificaciones?leido=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $leida->id);
    }

    public function test_paginacion_y_validacion_de_per_page(): void
    {
        Notificacion::factory()->count(5)->create(['usuario_id' => $this->usuario->id]);
        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/v1/notificaciones?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
        $this->getJson('/api/v1/notificaciones?per_page=2&page=3')->assertJsonCount(1, 'data')->assertJsonPath('links.next', null);
        $this->getJson('/api/v1/notificaciones?page=9')->assertOk()->assertJsonPath('data', []);

        foreach (['per_page=0', 'per_page=51', 'per_page=abc', 'page=0', 'leido=quizas'] as $consulta) {
            $this->getJson('/api/v1/notificaciones?'.$consulta)->assertUnprocessable();
        }
    }

    public function test_usuario_sin_notificaciones_recibe_pagina_vacia(): void
    {
        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/v1/notificaciones')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0)->assertJsonPath('meta.no_leidas', 0);
    }

    public function test_solicitud_id_puede_ser_null(): void
    {
        Notificacion::factory()->create(['usuario_id' => $this->usuario->id, 'solicitud_id' => null]);
        Sanctum::actingAs($this->usuario);

        $this->getJson('/api/v1/notificaciones')->assertJsonPath('data.0.solicitud_id', null);
    }

    public function test_listar_20_no_hace_mas_consultas_que_listar_2(): void
    {
        Sanctum::actingAs($this->usuario);
        $contar = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/notificaciones?per_page=50')->assertOk();
            $n = count(DB::getQueryLog());
            DB::flushQueryLog();

            return $n;
        };

        Notificacion::factory()->count(2)->create(['usuario_id' => $this->usuario->id]);
        $con2 = $contar();
        Notificacion::factory()->count(18)->create(['usuario_id' => $this->usuario->id]);
        $con20 = $contar();

        $this->assertLessThanOrEqual($con2, $con20);
    }

    public function test_listar_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/notificaciones')->assertUnauthorized();
    }

    public function test_marcar_como_leida(): void
    {
        $n = Notificacion::factory()->create(['usuario_id' => $this->usuario->id]);
        Sanctum::actingAs($this->usuario);

        $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')
            ->assertOk()
            ->assertJsonPath('data.id', $n->id)
            ->assertJsonPath('data.leido', true);
        $this->assertTrue($n->fresh()->leido);
    }

    public function test_marcar_es_idempotente(): void
    {
        $n = Notificacion::factory()->leida()->create(['usuario_id' => $this->usuario->id]);
        Sanctum::actingAs($this->usuario);

        $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')->assertOk()->assertJsonPath('data.leido', true);
        $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')->assertOk()->assertJsonPath('data.leido', true);
    }

    public function test_marcar_como_leida_baja_el_contador_de_no_leidas(): void
    {
        $n = Notificacion::factory()->count(2)->create(['usuario_id' => $this->usuario->id])->first();
        Sanctum::actingAs($this->usuario);

        $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')->assertOk();
        $this->getJson('/api/v1/notificaciones')->assertJsonPath('meta.no_leidas', 1);
    }

    public function test_no_se_puede_marcar_la_notificacion_de_otro_usuario(): void
    {
        $ajena = Notificacion::factory()->create();
        Sanctum::actingAs($this->usuario);

        $this->patchJson('/api/v1/notificaciones/'.$ajena->id.'/leer')
            ->assertForbidden()->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
        $this->assertFalse($ajena->fresh()->leido);
    }

    public function test_la_notificacion_de_otro_no_aparece_en_mi_listado(): void
    {
        $ajena = Notificacion::factory()->create();
        Notificacion::factory()->create(['usuario_id' => $this->usuario->id]);
        Sanctum::actingAs($this->usuario);

        $ids = collect($this->getJson('/api/v1/notificaciones')->json('data'))->pluck('id');
        $this->assertNotContains($ajena->id, $ids->all());
    }

    public function test_marcar_sin_token_da_401_y_si_no_existe_404(): void
    {
        $n = Notificacion::factory()->create(['usuario_id' => $this->usuario->id]);
        $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')->assertUnauthorized();

        Sanctum::actingAs($this->usuario);
        $this->patchJson('/api/v1/notificaciones/999999/leer')->assertNotFound();
    }

    public function test_cualquier_rol_tiene_sus_propias_notificaciones(): void
    {
        foreach ([User::factory()->responsable()->create(), User::factory()->administrador()->create(), User::factory()->personalAdministrativo()->create()] as $usuario) {
            $n = Notificacion::factory()->create(['usuario_id' => $usuario->id]);
            Sanctum::actingAs($usuario);

            $this->getJson('/api/v1/notificaciones')->assertOk()->assertJsonPath('data.0.id', $n->id);
            $this->patchJson('/api/v1/notificaciones/'.$n->id.'/leer')->assertOk();
        }
    }
}
