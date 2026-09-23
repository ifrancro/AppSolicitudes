<?php

namespace Tests\Feature\Api;

use App\Models\Solicitud;
use App\Models\TipoSolicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SolicitudCrearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpo(array $extra = []): array
    {
        return [
            'tipo_id' => TipoSolicitud::where('nombre', 'soporte_tecnologico')->value('id'),
            'titulo' => 'Proyector dañado',
            'descripcion' => 'No enciende.',
            'ubicacion' => 'Edificio B - Aula 204',
            ...$extra,
        ];
    }

    public function test_sin_token_da_401(): void
    {
        $this->postJson('/api/v1/solicitudes', $this->cuerpo())->assertStatus(401);
    }

    public function test_el_estudiante_crea_una_solicitud_pendiente_con_prioridad_media(): void
    {
        $estudiante = User::factory()->estudiante()->create();
        Sanctum::actingAs($estudiante);

        $this->postJson('/api/v1/solicitudes', $this->cuerpo())
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'Proyector dañado')
            ->assertJsonPath('data.ubicacion', 'Edificio B - Aula 204')
            ->assertJsonPath('data.estado.nombre', 'pendiente')
            ->assertJsonPath('data.prioridad.nombre', 'media')
            ->assertJsonPath('data.prioridad.nivel', 2)
            ->assertJsonPath('data.tipo.nombre', 'soporte_tecnologico')
            ->assertJsonPath('data.responsable', null)
            ->assertJsonMissingPath('data.estudiante')
            ->assertJsonStructure(['data' => [
                'id', 'titulo', 'descripcion', 'ubicacion', 'tipo' => ['id', 'nombre', 'descripcion'],
                'prioridad' => ['id', 'nombre', 'nivel'], 'estado' => ['id', 'nombre'],
                'responsable', 'created_at', 'updated_at',
            ]]);

        $this->assertDatabaseHas('solicitudes', ['estudiante_id' => $estudiante->id, 'titulo' => 'Proyector dañado']);
    }

    public function test_el_observer_escribe_el_historial_inicial(): void
    {
        $estudiante = User::factory()->estudiante()->create();
        Sanctum::actingAs($estudiante);

        $id = $this->postJson('/api/v1/solicitudes', $this->cuerpo())->json('data.id');

        $historial = Solicitud::findOrFail($id)->historialEstados;
        $this->assertCount(1, $historial);
        $this->assertSame('Solicitud registrada', $historial->first()->comentario);
        $this->assertSame($estudiante->id, $historial->first()->cambiado_por);
    }

    public function test_la_ubicacion_es_opcional(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $cuerpo = $this->cuerpo();
        unset($cuerpo['ubicacion']);

        $this->postJson('/api/v1/solicitudes', $cuerpo)->assertCreated()->assertJsonPath('data.ubicacion', null);
    }

    public function test_ignora_prioridad_estado_y_estudiante_enviados(): void
    {
        $estudiante = User::factory()->estudiante()->create();
        $otro = User::factory()->estudiante()->create();
        Sanctum::actingAs($estudiante);

        $this->postJson('/api/v1/solicitudes', $this->cuerpo([
            'prioridad_id' => 4,
            'estado_id' => 4,
            'estudiante_id' => $otro->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.estado.nombre', 'pendiente')
            ->assertJsonPath('data.prioridad.nombre', 'media');

        $this->assertDatabaseHas('solicitudes', ['estudiante_id' => $estudiante->id]);
        $this->assertDatabaseMissing('solicitudes', ['estudiante_id' => $otro->id]);
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
    public function test_otros_roles_reciben_403_y_no_se_crea_nada(string $rol): void
    {
        Sanctum::actingAs(User::factory()->{$rol}()->create());

        $this->postJson('/api/v1/solicitudes', $this->cuerpo())->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');
        $this->assertDatabaseCount('solicitudes', 0);
    }

    public function test_un_rol_ajeno_recibe_403_aunque_el_cuerpo_sea_invalido(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());

        $this->postJson('/api/v1/solicitudes', [])->assertForbidden();
    }

    public function test_campos_obligatorios(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->postJson('/api/v1/solicitudes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_id', 'titulo', 'descripcion'])
            ->assertJsonMissingValidationErrors(['ubicacion']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidos(): array
    {
        return [
            'tipo inexistente' => [['tipo_id' => 9999], 'tipo_id'],
            'tipo no entero' => [['tipo_id' => 'abc'], 'tipo_id'],
            'titulo demasiado largo' => [['titulo' => str_repeat('a', 151)], 'titulo'],
            'titulo no texto' => [['titulo' => ['x']], 'titulo'],
            'descripcion demasiado larga' => [['descripcion' => str_repeat('a', 2001)], 'descripcion'],
            'ubicacion demasiado larga' => [['ubicacion' => str_repeat('a', 256)], 'ubicacion'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_valida_cada_campo(array $cambio, string $campo): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->postJson('/api/v1/solicitudes', $this->cuerpo($cambio))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$campo]);
        $this->assertDatabaseCount('solicitudes', 0);
    }

    public function test_los_limites_exactos_son_validos(): void
    {
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->postJson('/api/v1/solicitudes', $this->cuerpo([
            'titulo' => str_repeat('a', 150),
            'descripcion' => str_repeat('b', 2000),
            'ubicacion' => str_repeat('c', 255),
        ]))->assertCreated();
    }
}
