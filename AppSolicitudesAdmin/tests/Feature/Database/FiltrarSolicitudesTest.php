<?php

namespace Tests\Feature\Database;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltrarSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_estado_y_prioridad(): void
    {
        Solicitud::factory()->count(2)->create();
        $objetivo = Solicitud::factory()->conEstado(EstadoNombre::EnProceso)->conPrioridad(PrioridadNombre::Alta)->create();

        $ids = Solicitud::query()->filtrar([
            'estado_id' => $objetivo->estado_id,
            'prioridad_id' => $objetivo->prioridad_id,
        ])->pluck('id');

        $this->assertSame([$objetivo->id], $ids->all());
    }

    public function test_busca_por_titulo_sin_distinguir_mayusculas_y_escapa_comodines(): void
    {
        $proyector = Solicitud::factory()->create(['titulo' => 'Proyector dañado']);
        Solicitud::factory()->create(['titulo' => 'Aire acondicionado']);
        Solicitud::factory()->create(['titulo' => '100% humedad']);

        $this->assertSame([$proyector->id], Solicitud::filtrar(['q' => 'PROYECTOR'])->pluck('id')->all());
        // "%" es un carácter literal, no un comodín que devuelva todo.
        $this->assertCount(1, Solicitud::filtrar(['q' => '100%'])->get());
    }

    public function test_filtra_por_rango_de_fechas_inclusivo(): void
    {
        $vieja = Solicitud::factory()->create(['created_at' => '2026-01-10 12:00:00']);
        $dentro = Solicitud::factory()->create(['created_at' => '2026-02-15 23:59:00']);
        Solicitud::factory()->create(['created_at' => '2026-03-20 00:00:00']);

        $ids = Solicitud::filtrar(['desde' => '2026-02-01', 'hasta' => '2026-02-15'])->pluck('id')->all();

        $this->assertSame([$dentro->id], $ids);
        $this->assertNotContains($vieja->id, $ids);
    }

    public function test_sin_asignar_y_responsable_consideran_solo_asignaciones_activas(): void
    {
        $responsable = User::factory()->responsable()->create();
        $asignada = Solicitud::factory()->create();
        $reasignada = Solicitud::factory()->create();
        $libre = Solicitud::factory()->create();

        Asignacion::factory()->create(['solicitud_id' => $asignada->id, 'responsable_id' => $responsable->id]);
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $reasignada->id, 'responsable_id' => $responsable->id]);

        $this->assertEqualsCanonicalizing(
            [$reasignada->id, $libre->id],
            Solicitud::filtrar(['sin_asignar' => true])->pluck('id')->all(),
        );
        $this->assertSame(
            [$asignada->id],
            Solicitud::filtrar(['responsable_id' => $responsable->id])->pluck('id')->all(),
        );
    }

    public function test_ordena_por_fecha_descendente_por_defecto_y_permite_ascendente(): void
    {
        $primera = Solicitud::factory()->create(['created_at' => '2026-01-01 10:00:00']);
        $segunda = Solicitud::factory()->create(['created_at' => '2026-01-02 10:00:00']);

        $this->assertSame([$segunda->id, $primera->id], Solicitud::filtrar([])->pluck('id')->all());
        $this->assertSame([$primera->id, $segunda->id], Solicitud::filtrar(['orden' => 'created_at'])->pluck('id')->all());
    }
}
