<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckCampusDatabase extends Command
{
    protected $signature = 'campus:database-check';

    protected $description = 'Comprueba PostgreSQL y la presencia de las tablas funcionales sin modificar datos';

    public function handle(): int
    {
        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                $this->error('La conexión configurada debe utilizar PostgreSQL.');

                return self::FAILURE;
            }

            // Consultar metadatos únicamente: no ejecutar migraciones ni leer usuarios.
            $rows = $connection->select(<<<'SQL'
                SELECT tablename
                FROM pg_catalog.pg_tables
                WHERE schemaname = current_schema()
                ORDER BY tablename
                SQL);
        } catch (Throwable) {
            // Las excepciones de conexión pueden incluir datos de configuración.
            $this->error('No se pudo consultar PostgreSQL. Revisa la conexión local sin compartir credenciales.');

            return self::FAILURE;
        }

        $tables = array_column($rows, 'tablename');
        $required = [
            'roles', 'usuarios', 'tipos_solicitud', 'prioridades',
            'estados_solicitud', 'solicitudes', 'adjuntos', 'asignaciones',
            'historial_estados', 'acciones', 'notificaciones',
        ];
        $missing = array_values(array_diff($required, $tables));

        $this->info('Conexión PostgreSQL: correcta.');
        $this->line('Comprobación de solo lectura del esquema actual.');

        if (in_array('users', $tables, true)) {
            $this->error('Se detectó users: revisar su integración con usuarios antes de continuar.');
        }

        if ($missing !== []) {
            $this->warn('Tablas funcionales ausentes: '.implode(', ', $missing).'.');
        }

        if ($missing !== [] || in_array('users', $tables, true)) {
            $this->line('No se ejecutaron migraciones ni se modificaron datos.');

            return self::FAILURE;
        }

        $this->info('Las 11 tablas funcionales están presentes.');
        $this->line('Este diagnóstico no valida columnas, restricciones, catálogos ni autenticación.');

        return self::SUCCESS;
    }
}
