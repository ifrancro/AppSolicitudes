<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoNombre;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class NotificacionCambioEstadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    public function test_crear_la_solicitud_no_genera_notificacion(): void
    {
        Solicitud::factory()->create();

        $this->assertSame(0, Notificacion::count());
    }

    public function test_cambiar_el_estado_notifica_al_estudiante_con_el_mensaje_exacto(): void
    {
        $solicitud = Solicitud::factory()->create(['titulo' => 'Proyector dañado']);
        $admin = User::factory()->administrador()->create();

        $solicitud->cambiarEstado(EstadoNombre::EnProceso, $admin);

        $n = Notificacion::firstOrFail();
        $this->assertSame($solicitud->estudiante_id, $n->usuario_id);
        $this->assertSame($solicitud->id, $n->solicitud_id);
        $this->assertSame('Tu solicitud «Proyector dañado» ahora está en proceso.', $n->mensaje);
        $this->assertFalse($n->leido);
    }

    public function test_el_nombre_del_estado_reemplaza_guiones_bajos_por_espacios(): void
    {
        $admin = User::factory()->administrador()->create();

        foreach ([
            [EstadoNombre::Asignada, 'asignada'],
            [EstadoNombre::EnProceso, 'en proceso'],
            [EstadoNombre::Cerrada, 'cerrada'],
            [EstadoNombre::Cancelada, 'cancelada'],
        ] as [$estado, $texto]) {
            $solicitud = Solicitud::factory()->create(['titulo' => 'T']);
            $solicitud->cambiarEstado($estado, $admin);

            $this->assertSame("Tu solicitud «T» ahora está {$texto}.", Notificacion::where('solicitud_id', $solicitud->id)->firstOrFail()->mensaje);
        }
    }

    public function test_no_se_notifica_al_estudiante_si_el_cambio_es_suyo(): void
    {
        $solicitud = Solicitud::factory()->create();

        $solicitud->cambiarEstado(EstadoNombre::Cancelada, $solicitud->estudiante);

        $this->assertSame(0, Notificacion::count());
    }

    public function test_cada_cambio_genera_una_notificacion_y_el_historial(): void
    {
        $solicitud = Solicitud::factory()->create();
        $responsable = User::factory()->responsable()->create();

        $solicitud->cambiarEstado(EstadoNombre::Asignada, $responsable);
        $solicitud->cambiarEstado(EstadoNombre::EnProceso, $responsable);

        $this->assertSame(2, Notificacion::where('usuario_id', $solicitud->estudiante_id)->count());
        $this->assertSame(3, $solicitud->historialEstados()->count());
    }

    public function test_un_cambio_que_no_toca_el_estado_no_notifica(): void
    {
        $solicitud = Solicitud::factory()->create();

        $solicitud->update(['titulo' => 'Otro titulo']);

        $this->assertSame(0, Notificacion::count());
    }

    public function test_la_notificacion_es_atomica_con_el_cambio_de_estado(): void
    {
        $solicitud = Solicitud::factory()->create();
        $admin = User::factory()->administrador()->create();

        try {
            DB::transaction(function () use ($solicitud, $admin) {
                $solicitud->cambiarEstado(EstadoNombre::Cerrada, $admin);
                throw new RuntimeException('fallo posterior');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Notificacion::count());
        $this->assertSame(1, $solicitud->historialEstados()->count());
    }
}
