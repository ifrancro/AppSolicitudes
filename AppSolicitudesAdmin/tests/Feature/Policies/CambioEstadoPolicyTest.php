<?php

namespace Tests\Feature\Policies;

use App\Enums\EstadoNombre;
use App\Models\Asignacion;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * SolicitudPolicy::changeStateTo(): quién puede pedir cada destino (contrato §6.7).
 */
class CambioEstadoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_state_to_por_rol_y_destino(): void
    {
        $solicitud = Solicitud::factory()->create();
        $asignado = User::factory()->responsable()->create();
        Asignacion::factory()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $asignado->id]);

        $esperado = [
            // [usuario, en_proceso, cerrada, cancelada, pendiente, asignada]
            'personal' => [User::factory()->personalAdministrativo()->create(), true, true, true, false, false],
            'admin' => [User::factory()->administrador()->create(), true, true, true, false, false],
            'responsable asignado' => [$asignado, true, true, false, false, false],
            'otro responsable' => [User::factory()->responsable()->create(), false, false, false, false, false],
            'estudiante dueño' => [$solicitud->estudiante, false, false, false, false, false],
        ];

        $destinos = [EstadoNombre::EnProceso, EstadoNombre::Cerrada, EstadoNombre::Cancelada, EstadoNombre::Pendiente, EstadoNombre::Asignada];

        foreach ($esperado as $quien => $fila) {
            $usuario = array_shift($fila);

            foreach ($destinos as $i => $destino) {
                $this->assertSame(
                    $fila[$i],
                    Gate::forUser($usuario)->allows('changeStateTo', [$solicitud, $destino]),
                    "{$quien} -> {$destino->value}",
                );
            }
        }
    }

    public function test_un_responsable_con_asignacion_finalizada_no_puede_pedir_ningun_destino(): void
    {
        $solicitud = Solicitud::factory()->create();
        $anterior = User::factory()->responsable()->create();
        Asignacion::factory()->finalizada()->create(['solicitud_id' => $solicitud->id, 'responsable_id' => $anterior->id]);

        foreach ([EstadoNombre::EnProceso, EstadoNombre::Cerrada] as $destino) {
            $this->assertFalse(Gate::forUser($anterior)->allows('changeStateTo', [$solicitud, $destino]));
        }
    }
}
