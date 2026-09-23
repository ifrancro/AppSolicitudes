<?php

namespace Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CheckCampusDatabaseTest extends TestCase
{
    public function test_it_rejects_other_drivers_without_querying_them(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $connection->shouldNotReceive('select');
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->artisan('campus:database-check')
            ->expectsOutput('La conexión configurada debe utilizar PostgreSQL.')
            ->assertFailed();
    }

    public function test_an_empty_database_reports_missing_tables_without_creating_them(): void
    {
        $this->mockTables([]);

        $this->artisan('campus:database-check')
            ->expectsOutput('Conexión PostgreSQL: correcta.')
            ->expectsOutputToContain('Tablas funcionales ausentes: roles, usuarios')
            ->expectsOutput('No se ejecutaron migraciones ni se modificaron datos.')
            ->assertFailed();
    }

    public function test_it_accepts_functional_tables_alongside_technical_tables(): void
    {
        $this->mockTables([...$this->functionalTables(), 'migrations', 'sessions']);

        $this->artisan('campus:database-check')
            ->expectsOutput('Las 11 tablas funcionales están presentes.')
            ->expectsOutput('Este diagnóstico no valida columnas, restricciones, catálogos ni autenticación.')
            ->assertSuccessful();
    }

    public function test_it_reports_an_incomplete_schema(): void
    {
        $this->mockTables(array_diff($this->functionalTables(), ['solicitudes']));

        $this->artisan('campus:database-check')
            ->expectsOutput('Tablas funcionales ausentes: solicitudes.')
            ->assertFailed();
    }

    public function test_it_rejects_a_parallel_users_table(): void
    {
        $this->mockTables([...$this->functionalTables(), 'users']);

        $this->artisan('campus:database-check')
            ->expectsOutput('Se detectó users: revisar su integración con usuarios antes de continuar.')
            ->assertFailed();
    }

    public function test_connection_errors_do_not_expose_configuration(): void
    {
        DB::shouldReceive('connection')->once()
            ->andThrow(new RuntimeException('PRIVATE_CONNECTION_DETAIL'));

        $this->artisan('campus:database-check')
            ->expectsOutput('No se pudo consultar PostgreSQL. Revisa la conexión local sin compartir credenciales.')
            ->doesntExpectOutputToContain('PRIVATE_CONNECTION_DETAIL')
            ->assertFailed();
    }

    private function mockTables(array $tables): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('select')->once()
            ->with(Mockery::on(fn (string $sql): bool => str_starts_with(trim($sql), 'SELECT tablename')
                && str_contains($sql, 'pg_catalog.pg_tables')
                && str_contains($sql, 'schemaname = current_schema()')
            ))
            ->andReturn(array_map(fn (string $table): object => (object) ['tablename' => $table], array_values($tables)));
        $connection->shouldNotReceive('statement');
        $connection->shouldNotReceive('unprepared');
        DB::shouldReceive('connection')->once()->andReturn($connection);
    }

    private function functionalTables(): array
    {
        return [
            'roles', 'usuarios', 'tipos_solicitud', 'prioridades',
            'estados_solicitud', 'solicitudes', 'adjuntos', 'asignaciones',
            'historial_estados', 'acciones', 'notificaciones',
        ];
    }
}
