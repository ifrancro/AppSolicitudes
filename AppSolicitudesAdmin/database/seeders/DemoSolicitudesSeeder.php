<?php

namespace Database\Seeders;

use App\Enums\EstadoNombre;
use App\Enums\PrioridadNombre;
use App\Enums\TipoNombre;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\AccionService;
use App\Services\Solicitudes\AsignacionService;
use App\Services\Solicitudes\CambioEstadoService;
use Illuminate\Database\Seeder;

/**
 * Solicitudes de ejemplo en todos los estados, para probar la app y el panel.
 * Solo se ejecuta en entorno local y una única vez.
 */
class DemoSolicitudesSeeder extends Seeder
{
    public function run(
        AsignacionService $asignaciones,
        CambioEstadoService $estados,
        AccionService $acciones,
    ): void {
        if (Solicitud::query()->exists()) {
            return;
        }

        $estudiante = User::where('email', 'estudiante@campus.test')->firstOrFail();
        $admin = User::where('email', 'admin@campus.test')->firstOrFail();
        $responsable = User::where('email', 'responsable@campus.test')->firstOrFail();

        $casos = [
            ['Proyector del aula 204 no enciende', TipoNombre::SoporteTecnologico, PrioridadNombre::Alta, 'Edificio B - Aula 204', 'pendiente'],
            ['Filtración de agua en el baño del 2.º piso', TipoNombre::Mantenimiento, PrioridadNombre::Urgente, 'Edificio A - Piso 2', 'pendiente'],
            ['Sin internet en el laboratorio 3', TipoNombre::SoporteTecnologico, PrioridadNombre::Media, 'Laboratorio 3', 'asignada'],
            ['Faltan sillas en la biblioteca', TipoNombre::Equipamiento, PrioridadNombre::Baja, 'Biblioteca central', 'asignada'],
            ['Puerta del auditorio no cierra', TipoNombre::Infraestructura, PrioridadNombre::Media, 'Auditorio', 'en_proceso'],
            ['Lámpara fundida en el pasillo', TipoNombre::Mantenimiento, PrioridadNombre::Baja, 'Edificio C - Pasillo', 'en_proceso'],
            ['Cuenta institucional bloqueada', TipoNombre::SoporteTecnologico, PrioridadNombre::Alta, null, 'cerrada'],
            ['Grieta en la pared de la cafetería', TipoNombre::Infraestructura, PrioridadNombre::Alta, 'Cafetería', 'cerrada'],
            ['Solicitud duplicada de mantenimiento', TipoNombre::Otros, PrioridadNombre::Baja, null, 'cancelada'],
        ];

        foreach ($casos as $i => [$titulo, $tipo, $prioridad, $ubicacion, $destino]) {
            $solicitud = Solicitud::factory()
                ->conTipo($tipo)
                ->conPrioridad($prioridad)
                ->create([
                    'estudiante_id' => $estudiante->id,
                    'titulo' => $titulo,
                    'descripcion' => 'Ejemplo de datos de demostración: '.$titulo.'.',
                    'ubicacion' => $ubicacion,
                    'created_at' => now()->subDays(20 - $i * 2),
                ]);

            if ($destino === 'cancelada') {
                $estados->cambiar($solicitud, EstadoNombre::Cancelada, $admin, 'Ya existía otra solicitud igual.');

                continue;
            }

            if ($destino === 'pendiente') {
                continue;
            }

            $asignaciones->asignar($solicitud, $responsable, $admin, 'Asignada desde los datos de demostración.');

            if (in_array($destino, ['en_proceso', 'cerrada'], true)) {
                $estados->cambiar($solicitud, EstadoNombre::EnProceso, $responsable, 'Revisando.');
                $acciones->registrar($solicitud, $responsable, 'Se revisó el problema en el lugar.');
            }

            if ($destino === 'cerrada') {
                $estados->cambiar($solicitud, EstadoNombre::Cerrada, $responsable, 'Resuelto.');
            }
        }
    }
}
