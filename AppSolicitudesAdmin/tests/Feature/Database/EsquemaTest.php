<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\HistorialEstado;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosSeeder::class);
    }

    public function test_existen_las_once_tablas_y_no_la_tabla_users(): void
    {
        foreach ([
            'roles', 'usuarios', 'tipos_solicitud', 'prioridades', 'estados_solicitud',
            'solicitudes', 'adjuntos', 'asignaciones', 'historial_estados', 'acciones',
            'notificaciones',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }

        $this->assertFalse(Schema::hasTable('users'));
    }

    public function test_existen_los_indices_requeridos(): void
    {
        $this->assertTrue($this->tieneIndice('solicitudes', ['estudiante_id']));
        $this->assertTrue($this->tieneIndice('solicitudes', ['estado_id']));
        $this->assertTrue($this->tieneIndice('solicitudes', ['tipo_id']));
        $this->assertTrue($this->tieneIndice('asignaciones', ['solicitud_id', 'activo']));
        $this->assertTrue($this->tieneIndice('notificaciones', ['usuario_id', 'leido']));
    }

    public function test_los_seeders_cargan_catalogos_y_un_usuario_por_rol(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotente

        $this->assertSame(4, DB::table('roles')->count());
        $this->assertSame(5, DB::table('tipos_solicitud')->count());
        $this->assertSame(4, DB::table('prioridades')->count());
        $this->assertSame(5, DB::table('estados_solicitud')->count());
        $this->assertSame(4, User::count());
    }

    public function test_la_autenticacion_usa_password_hash(): void
    {
        $usuario = User::factory()->create(['email' => 'a@campus.test', 'password_hash' => 'secreto']);

        $this->assertNotSame('secreto', $usuario->password_hash);
        $this->assertTrue(Hash::check('secreto', $usuario->getAuthPassword()));
        $this->assertTrue(Auth::validate(['email' => 'a@campus.test', 'password' => 'secreto']));
        $this->assertFalse(Auth::validate(['email' => 'a@campus.test', 'password' => 'otra']));
    }

    public function test_crear_una_solicitud_escribe_el_primer_historial(): void
    {
        $solicitud = Solicitud::factory()->create();

        $this->assertSame(1, HistorialEstado::where('solicitud_id', $solicitud->id)->count());
        $this->assertSame($solicitud->estudiante_id, HistorialEstado::first()->cambiado_por);
    }

    public function test_cambiar_estado_escribe_historial_con_autor_y_comentario(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();

        $solicitud->cambiarEstado(EstadoNombre::EnProceso, $responsable, 'Empezamos hoy');

        $ultimo = HistorialEstado::where('solicitud_id', $solicitud->id)->latest('id')->first();
        $this->assertSame($responsable->id, $ultimo->cambiado_por);
        $this->assertSame('Empezamos hoy', $ultimo->comentario);
        $this->assertSame(EstadoNombre::EnProceso->value, $ultimo->estado->nombre);
    }

    public function test_un_update_directo_del_estado_sin_usuario_falla_en_voz_alta(): void
    {
        $solicitud = Solicitud::factory()->create();
        $otroEstado = Solicitud::factory()->conEstado(EstadoNombre::Cerrada)->create()->estado_id;

        $this->expectException(LogicException::class);

        $solicitud->update(['estado_id' => $otroEstado]);
    }

    public function test_solo_puede_haber_una_asignacion_activa_por_solicitud(): void
    {
        $solicitud = Solicitud::factory()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id]);

        $this->expectException(QueryException::class);

        Asignacion::factory()->create(['solicitud_id' => $solicitud->id]);
    }

    /**
     * @param  array<int, string>  $columnas
     */
    private function tieneIndice(string $tabla, array $columnas): bool
    {
        foreach (Schema::getIndexes($tabla) as $indice) {
            if ($indice['columns'] === $columnas) {
                return true;
            }
        }

        return false;
    }
}
