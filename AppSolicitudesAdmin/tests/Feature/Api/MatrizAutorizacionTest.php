<?php

namespace Tests\Feature\Api;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prueba transversal de la API: recorre TODAS las rutas de /api/v1 y comprueba,
 * para cada rol, que se deniega lo que el contrato (§2) no permite y que no se
 * bloquea lo que sí permite. Complementa las pruebas por fase.
 *
 * "Permitido" significa "la autorización no lo rechaza": la petición se envía
 * vacía a propósito, así que el resultado esperado es 200/201 o 422 (validación),
 * nunca 401 ni 403.
 */
class MatrizAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    private const ESTUDIANTE = 'estudiante';

    private const OTRO_ESTUDIANTE = 'otro_estudiante';

    private const PERSONAL = 'personal';

    private const ADMIN = 'admin';

    private const RESPONSABLE = 'responsable_asignado';

    private const OTRO_RESPONSABLE = 'otro_responsable';

    private const TODOS = [
        self::ESTUDIANTE, self::OTRO_ESTUDIANTE, self::PERSONAL, self::ADMIN,
        self::RESPONSABLE, self::OTRO_RESPONSABLE,
    ];

    /** @var array<string, User> */
    private array $usuarios;

    private Solicitud $solicitud;

    private Notificacion $notificacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosSeeder::class);

        $this->usuarios = [
            self::ESTUDIANTE => User::factory()->estudiante()->create(),
            self::OTRO_ESTUDIANTE => User::factory()->estudiante()->create(),
            self::PERSONAL => User::factory()->personalAdministrativo()->create(),
            self::ADMIN => User::factory()->administrador()->create(),
            self::RESPONSABLE => User::factory()->responsable()->create(),
            self::OTRO_RESPONSABLE => User::factory()->responsable()->create(),
        ];

        $this->solicitud = Solicitud::factory()->create(['estudiante_id' => $this->usuarios[self::ESTUDIANTE]->id]);
        Asignacion::factory()->create([
            'solicitud_id' => $this->solicitud->id,
            'responsable_id' => $this->usuarios[self::RESPONSABLE]->id,
            'asignado_por' => $this->usuarios[self::ADMIN]->id,
        ]);
        $this->solicitud->cambiarEstado(EstadoNombre::Asignada, $this->usuarios[self::ADMIN]);

        $this->notificacion = Notificacion::factory()->create([
            'usuario_id' => $this->usuarios[self::ESTUDIANTE]->id,
            'solicitud_id' => $this->solicitud->id,
        ]);
    }

    /**
     * Método, ruta y roles a los que el contrato permite llamar. `{s}` es la solicitud.
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    public static function endpoints(): array
    {
        $gestion = [self::PERSONAL, self::ADMIN];
        $gestionYAsignado = [self::PERSONAL, self::ADMIN, self::RESPONSABLE];
        $veSolicitud = [self::ESTUDIANTE, self::PERSONAL, self::ADMIN, self::RESPONSABLE];

        return [
            'solicitudes.store' => ['POST', '/solicitudes', [self::ESTUDIANTE, self::OTRO_ESTUDIANTE]],
            'mis-solicitudes' => ['GET', '/mis-solicitudes', [self::ESTUDIANTE, self::OTRO_ESTUDIANTE]],
            'solicitudes.show' => ['GET', '/solicitudes/{s}', $veSolicitud],
            'historial-estados' => ['GET', '/solicitudes/{s}/historial-estados', $veSolicitud],
            'adjuntos.store' => ['POST', '/solicitudes/{s}/adjuntos', [self::ESTUDIANTE]],
            'adjuntos.index' => ['GET', '/solicitudes/{s}/adjuntos', $veSolicitud],
            'solicitudes.index' => ['GET', '/solicitudes', $gestion],
            'clasificacion' => ['PATCH', '/solicitudes/{s}/clasificacion', $gestion],
            'responsables' => ['GET', '/usuarios/responsables', $gestion],
            'asignaciones.store' => ['POST', '/solicitudes/{s}/asignaciones', $gestion],
            'asignaciones.index' => ['GET', '/solicitudes/{s}/asignaciones', $gestionYAsignado],
            'solicitudes-asignadas' => ['GET', '/solicitudes-asignadas', [self::RESPONSABLE, self::OTRO_RESPONSABLE]],
            'estado' => ['PATCH', '/solicitudes/{s}/estado', $gestionYAsignado],
            'acciones.store' => ['POST', '/solicitudes/{s}/acciones', [self::RESPONSABLE]],
            'acciones.index' => ['GET', '/solicitudes/{s}/acciones', $gestionYAsignado],
            'dashboard' => ['GET', '/dashboard/resumen', [self::ADMIN]],
            'reportes.solicitudes' => ['GET', '/reportes/solicitudes', [self::ADMIN]],
            'reportes.por-tipo' => ['GET', '/reportes/solicitudes-por-tipo', [self::ADMIN]],
            'reportes.tiempos' => ['GET', '/reportes/tiempos-atencion', [self::ADMIN]],
            'notificaciones.leer' => ['PATCH', '/notificaciones/{n}/leer', [self::ESTUDIANTE]],
        ];
    }

    /**
     * @param  array<int, string>  $permitidos
     */
    #[DataProvider('endpointsProvider')]
    public function test_cada_rol_recibe_403_o_pasa_la_autorizacion_segun_el_contrato(string $metodo, string $ruta, array $permitidos): void
    {
        $url = '/api/v1'.$this->resolver($ruta);

        foreach (self::TODOS as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            $respuesta = $this->json($metodo, $url);

            if (in_array($rol, $permitidos, true)) {
                $this->assertNotContains(
                    $respuesta->getStatusCode(),
                    [401, 403],
                    "{$metodo} {$ruta} debería permitirse a {$rol}, respondió {$respuesta->getStatusCode()}",
                );
            } else {
                $this->assertSame(
                    403,
                    $respuesta->getStatusCode(),
                    "{$metodo} {$ruta} debería denegarse (403) a {$rol}, respondió {$respuesta->getStatusCode()}",
                );
                $respuesta->assertExactJson(['message' => 'No tienes permiso para realizar esta acción.']);
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>}>
     */
    public static function endpointsProvider(): array
    {
        return self::endpoints();
    }

    public function test_una_notificacion_ajena_da_403_incluso_a_un_rol_de_gestion(): void
    {
        foreach ([self::OTRO_ESTUDIANTE, self::PERSONAL, self::ADMIN, self::RESPONSABLE] as $rol) {
            Sanctum::actingAs($this->usuarios[$rol]);

            $this->patchJson("/api/v1/notificaciones/{$this->notificacion->id}/leer")->assertForbidden();
        }

        $this->assertFalse($this->notificacion->fresh()->leido);
    }

    public function test_todas_las_rutas_protegidas_devuelven_401_sin_token(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'api/v1/'))
            ->reject(fn ($ruta) => in_array($ruta->uri(), ['api/v1/ping', 'api/v1/auth/login'], true));

        // 27 endpoints del contrato menos ping y login (públicos) ya contados aparte.
        $this->assertGreaterThanOrEqual(26, $rutas->count());

        foreach ($rutas as $ruta) {
            $url = '/'.preg_replace('/\{[^}]+\}/', '1', $ruta->uri());
            $metodo = collect($ruta->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'], true));

            $this->json($metodo, $url)
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'No autenticado.']);
        }
    }

    public function test_ping_y_login_son_los_unicos_publicos(): void
    {
        $this->getJson('/api/v1/ping')->assertOk();
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
    }

    public function test_un_recurso_inexistente_da_404_a_quien_tiene_permiso_de_rol(): void
    {
        Sanctum::actingAs($this->usuarios[self::ADMIN]);

        $this->getJson('/api/v1/solicitudes/999999')->assertNotFound();
        $this->getJson('/api/v1/solicitudes/999999/asignaciones')->assertNotFound();
        $this->patchJson('/api/v1/solicitudes/999999/estado')->assertNotFound();
    }

    private function resolver(string $ruta): string
    {
        return str_replace(['{s}', '{n}'], [(string) $this->solicitud->id, (string) $this->notificacion->id], $ruta);
    }
}
