<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\EstadoSolicitud;
use App\Models\HistorialEstado;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\CambioEstadoService;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrato §6.7: PATCH /solicitudes/{id}/estado.
 */
class CambioEstadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    /**
     * Los 25 pares (desde, hacia) de la matriz completa.
     *
     * @return array<string, array{EstadoNombre, EstadoNombre}>
     */
    public static function matriz(): array
    {
        $pares = [];

        foreach (EstadoNombre::cases() as $desde) {
            foreach (EstadoNombre::cases() as $hacia) {
                $pares["{$desde->value} -> {$hacia->value}"] = [$desde, $hacia];
            }
        }

        return $pares;
    }

    // --- Tabla de transiciones (enum) ---

    public function test_la_tabla_de_transiciones_legales_es_la_del_contrato(): void
    {
        $legales = [
            'pendiente' => ['asignada', 'cancelada'],
            'asignada' => ['en_proceso', 'cancelada'],
            'en_proceso' => ['cerrada', 'cancelada'],
            'cerrada' => [],
            'cancelada' => [],
        ];

        foreach (EstadoNombre::cases() as $desde) {
            foreach (EstadoNombre::cases() as $hacia) {
                $this->assertSame(
                    in_array($hacia->value, $legales[$desde->value], true),
                    $desde->puedePasarA($hacia),
                    "{$desde->value} -> {$hacia->value}",
                );
            }
        }

        $this->assertTrue(EstadoNombre::Cerrada->esFinal());
        $this->assertTrue(EstadoNombre::Cancelada->esFinal());
        $this->assertFalse(EstadoNombre::EnProceso->esFinal());
    }

    // --- Matriz completa por la API ---

    #[DataProvider('matriz')]
    public function test_matriz_completa_como_administrador(EstadoNombre $desde, EstadoNombre $hacia): void
    {
        $admin = User::factory()->administrador()->create();
        $solicitud = Solicitud::factory()->conEstado($desde)->create();
        Sanctum::actingAs($admin);

        $antes = HistorialEstado::where('solicitud_id', $solicitud->id)->count();

        $respuesta = $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
            'estado_id' => EstadoSolicitud::idDe($hacia),
            'comentario' => 'Motivo del cambio',
        ]);

        $manual = in_array($hacia, CambioEstadoService::DESTINOS_MANUALES, true);

        if ($manual && $desde->puedePasarA($hacia)) {
            $respuesta->assertOk()
                ->assertJsonPath('data.id', $solicitud->id)
                ->assertJsonPath('data.estado.nombre', $hacia->value);

            $this->assertSame($antes + 1, HistorialEstado::where('solicitud_id', $solicitud->id)->count());
            $fila = HistorialEstado::where('solicitud_id', $solicitud->id)->latest('id')->first();
            $this->assertSame(EstadoSolicitud::idDe($hacia), $fila->estado_id);
            $this->assertSame($admin->id, $fila->cambiado_por);
            $this->assertSame('Motivo del cambio', $fila->comentario);
        } else {
            $respuesta->assertStatus(422)->assertJsonValidationErrors('estado_id');

            $this->assertSame($desde->value, $solicitud->fresh()->estado->nombre);
            $this->assertSame($antes, HistorialEstado::where('solicitud_id', $solicitud->id)->count());
        }
    }

    #[DataProvider('matriz')]
    public function test_matriz_completa_como_responsable_asignado(EstadoNombre $desde, EstadoNombre $hacia): void
    {
        $responsable = User::factory()->responsable()->create();
        $solicitud = Solicitud::factory()->conEstado($desde)->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);
        Sanctum::actingAs($responsable);

        $respuesta = $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
            'estado_id' => EstadoSolicitud::idDe($hacia),
            'comentario' => 'Motivo del cambio',
        ]);

        $manual = in_array($hacia, CambioEstadoService::DESTINOS_MANUALES, true);

        if ($hacia === EstadoNombre::Cancelada) {
            // Cancelar no le corresponde al responsable, sea cual sea el estado actual.
            $respuesta->assertForbidden();
            $this->assertSame($desde->value, $solicitud->fresh()->estado->nombre);
        } elseif ($manual && $desde->puedePasarA($hacia)) {
            $respuesta->assertOk()->assertJsonPath('data.estado.nombre', $hacia->value);
        } else {
            $respuesta->assertStatus(422)->assertJsonValidationErrors('estado_id');
            $this->assertSame($desde->value, $solicitud->fresh()->estado->nombre);
        }
    }

    // --- Quién puede pedir cada destino ---

    public function test_personal_y_administrador_piden_los_tres_destinos_legales(): void
    {
        foreach ([User::factory()->personalAdministrativo()->create(), User::factory()->administrador()->create()] as $usuario) {
            Sanctum::actingAs($usuario);

            $enProceso = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();
            $this->patchJson("/api/v1/solicitudes/{$enProceso->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso)])->assertOk();

            $cerrada = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->create();
            $this->patchJson("/api/v1/solicitudes/{$cerrada->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cerrada)])->assertOk();

            $cancelada = Solicitud::factory()->conEstado(EstadoNombre::Pendiente)->create();
            $this->patchJson("/api/v1/solicitudes/{$cancelada->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cancelada), 'comentario' => 'Duplicada'])->assertOk();
        }
    }

    public function test_el_responsable_asignado_pide_en_proceso_y_cerrada_pero_no_cancelada(): void
    {
        $responsable = User::factory()->responsable()->create();
        Sanctum::actingAs($responsable);

        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cancelada), 'comentario' => 'x'])
            ->assertForbidden();

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso)])->assertOk();
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cerrada), 'comentario' => 'Listo'])->assertOk();
    }

    public function test_estudiante_dueno_no_puede_cambiar_el_estado(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Pendiente)->create();
        Sanctum::actingAs($solicitud->estudiante);

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cancelada), 'comentario' => 'x'])
            ->assertForbidden();

        $this->assertSame('pendiente', $solicitud->fresh()->estado->nombre);
    }

    public function test_responsable_de_otra_solicitud_recibe_403(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
        Sanctum::actingAs(User::factory()->responsable()->create());

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso)])
            ->assertForbidden();
    }

    public function test_responsable_con_asignacion_finalizada_recibe_403(): void
    {
        $anterior = User::factory()->responsable()->create();
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $anterior->id]);
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
        Sanctum::actingAs($anterior);

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso)])
            ->assertForbidden();

        $this->assertSame('asignada', $solicitud->fresh()->estado->nombre);
    }

    // --- Validación, 401 y 404 ---

    public function test_sin_token_da_401_y_solicitud_inexistente_da_404(): void
    {
        $this->patchJson('/api/v1/solicitudes/1/estado', ['estado_id' => 3])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->administrador()->create());
        $this->patchJson('/api/v1/solicitudes/99999/estado', ['estado_id' => 3])->assertNotFound();
    }

    public function test_cancelar_sin_comentario_da_422_en_comentario(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Pendiente)->create();

        foreach ([[], ['comentario' => ''], ['comentario' => '   ']] as $cuerpo) {
            $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cancelada)] + $cuerpo)
                ->assertStatus(422)
                ->assertJsonValidationErrors('comentario');
        }

        $this->assertSame('pendiente', $solicitud->fresh()->estado->nombre);
    }

    public function test_valida_estado_inexistente_ausente_y_comentario_largo(): void
    {
        Sanctum::actingAs(User::factory()->administrador()->create());
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('estado_id');
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [])
            ->assertStatus(422)->assertJsonValidationErrors('estado_id');
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso), 'comentario' => str_repeat('a', 501)])
            ->assertStatus(422)->assertJsonValidationErrors('comentario');
    }

    public function test_403_gana_sobre_422_para_quien_no_tiene_acceso(): void
    {
        $solicitud = Solicitud::factory()->create();
        Sanctum::actingAs(User::factory()->estudiante()->create());

        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => 999])->assertForbidden();
    }

    // --- Bitácora y servicio ---

    public function test_cada_cambio_deja_su_fila_con_autor_y_comentario_en_orden(): void
    {
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();
        $responsable = User::factory()->responsable()->create();
        $admin = User::factory()->administrador()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $responsable->id]);

        Sanctum::actingAs($responsable);
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso), 'comentario' => 'Iniciamos la revisión'])->assertOk();
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::EnProceso)])->assertStatus(422);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", ['estado_id' => EstadoSolicitud::idDe(EstadoNombre::Cancelada), 'comentario' => 'Ya no se necesita'])->assertOk();

        $filas = HistorialEstado::where('solicitud_id', $solicitud->id)->orderBy('id')->get();
        // 1 fila de creación + 2 cambios; el intento ilegal no escribe nada.
        $this->assertCount(3, $filas);
        $this->assertSame([$responsable->id, $admin->id], $filas->slice(1)->pluck('cambiado_por')->values()->all());
        $this->assertSame(['Iniciamos la revisión', 'Ya no se necesita'], $filas->slice(1)->pluck('comentario')->values()->all());
    }

    public function test_el_servicio_funciona_sin_http_y_lanza_validation_exception(): void
    {
        $servicio = app(CambioEstadoService::class);
        $actor = User::factory()->administrador()->create();
        $solicitud = Solicitud::factory()->conEstado(EstadoNombre::Asignada)->create();

        $resultado = $servicio->cambiar($solicitud, EstadoNombre::EnProceso, $actor, null);
        $this->assertSame('en_proceso', $resultado->estado->nombre);

        try {
            $servicio->cambiar($solicitud, EstadoNombre::Asignada, $actor, null);
            $this->fail('Debió lanzar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estado_id', $e->errors());
        }
    }
}
