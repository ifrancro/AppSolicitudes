<?php

namespace App\Services\Reportes;

use App\Enums\EstadoNombre;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cifras del dashboard y de los reportes (contrato §6.10, §6.12 y §6.13).
 *
 * Independiente de HTTP para que el panel Blade la reutilice. Todo sale de
 * consultas agregadas: el número de consultas no depende del número de
 * solicitudes.
 */
class ReportesService
{
    /**
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        $estados = $this->catalogo('estados_solicitud');
        $idPendiente = $this->idEstado($estados, EstadoNombre::Pendiente);
        $idCerrada = $this->idEstado($estados, EstadoNombre::Cerrada);
        $hace30Dias = Carbon::now()->subDays(30);

        $fila = DB::table('solicitudes as s')
            ->leftJoinSub($this->cierres($idCerrada), 'c', 'c.solicitud_id', '=', 's.id')
            ->selectRaw(
                'COUNT(*) AS total,
                 COUNT(*) FILTER (WHERE s.estado_id = ? AND NOT EXISTS (
                     SELECT 1 FROM asignaciones a WHERE a.solicitud_id = s.id AND a.activo = true
                 )) AS sin_asignar,
                 COUNT(*) FILTER (WHERE s.estado_id = ? AND c.cerrada_at >= ?) AS cerradas_30,
                 ROUND((AVG(EXTRACT(EPOCH FROM (c.cerrada_at - s.created_at)) / 3600)
                     FILTER (WHERE s.estado_id = ?))::numeric, 2) AS promedio',
                [$idPendiente, $idCerrada, $hace30Dias, $idCerrada],
            )
            ->first();

        return [
            'total' => (int) $fila->total,
            'sin_asignar' => (int) $fila->sin_asignar,
            'por_estado' => $this->conteoPor('estado_id', 'estados_solicitud'),
            'por_prioridad' => $this->conteoPor('prioridad_id', 'prioridades'),
            'por_tipo' => $this->conteoPor('tipo_id', 'tipos_solicitud'),
            'cerradas_ultimos_30_dias' => (int) $fila->cerradas_30,
            'tiempo_promedio_atencion_horas' => $this->decimal($fila->promedio),
        ];
    }

    /**
     * @param  array{desde?: string, hasta?: string}  $filtros
     * @return list<array<string, mixed>>
     */
    public function solicitudesPorTipo(array $filtros = []): array
    {
        $estados = $this->catalogo('estados_solicitud');

        $filas = $this->solicitudesFiltradas($filtros)
            ->selectRaw('s.tipo_id, s.estado_id, COUNT(*) AS total')
            ->groupBy('s.tipo_id', 's.estado_id')
            ->get();

        $cuentas = [];
        foreach ($filas as $fila) {
            $cuentas[$fila->tipo_id][$fila->estado_id] = (int) $fila->total;
        }

        $resultado = [];
        foreach ($this->catalogo('tipos_solicitud') as $tipoId => $nombre) {
            $porEstado = [];
            foreach ($estados as $estadoId => $estadoNombre) {
                $porEstado[$estadoNombre] = $cuentas[$tipoId][$estadoId] ?? 0;
            }

            $resultado[] = [
                'tipo_id' => $tipoId,
                'tipo' => $nombre,
                'total' => array_sum($porEstado),
                'por_estado' => $porEstado,
            ];
        }

        return $resultado;
    }

    /**
     * @param  array{desde?: string, hasta?: string}  $filtros
     * @return array<string, mixed>
     */
    public function tiemposAtencion(array $filtros = []): array
    {
        $idCerrada = $this->idEstado($this->catalogo('estados_solicitud'), EstadoNombre::Cerrada);
        $horas = 'EXTRACT(EPOCH FROM (c.cerrada_at - s.created_at)) / 3600';

        $cerradas = fn () => $this->solicitudesFiltradas($filtros)
            ->joinSub($this->cierres($idCerrada), 'c', 'c.solicitud_id', '=', 's.id')
            ->where('s.estado_id', $idCerrada);

        $global = $cerradas()
            ->selectRaw("COUNT(*) AS total, ROUND((AVG($horas))::numeric, 2) AS promedio, ROUND((MIN($horas))::numeric, 2) AS minimo, ROUND((MAX($horas))::numeric, 2) AS maximo")
            ->first();

        $porTipo = $cerradas()
            ->selectRaw("s.tipo_id, COUNT(*) AS total, ROUND((AVG($horas))::numeric, 2) AS promedio")
            ->groupBy('s.tipo_id')
            ->get()
            ->keyBy('tipo_id');

        $tipos = [];
        foreach ($this->catalogo('tipos_solicitud') as $tipoId => $nombre) {
            $fila = $porTipo->get($tipoId);
            $tipos[] = [
                'tipo_id' => $tipoId,
                'tipo' => $nombre,
                'total_cerradas' => $fila ? (int) $fila->total : 0,
                'promedio_horas' => $fila ? $this->decimal($fila->promedio) : null,
            ];
        }

        return [
            'total_cerradas' => (int) $global->total,
            'promedio_horas' => $this->decimal($global->promedio),
            'minimo_horas' => $this->decimal($global->minimo),
            'maximo_horas' => $this->decimal($global->maximo),
            'por_tipo' => $tipos,
        ];
    }

    /**
     * Subconsulta correlacionada con la fecha del último paso a `cerrada`;
     * sirve para añadir `cerrada_at` a un listado de `solicitudes` sin
     * consultar por fila.
     */
    public function subconsultaCerradaAt(): Builder
    {
        $idCerrada = $this->idEstado($this->catalogo('estados_solicitud'), EstadoNombre::Cerrada);

        return DB::table('historial_estados as h')
            ->selectRaw('MAX(h.created_at)')
            ->whereColumn('h.solicitud_id', 'solicitudes.id')
            ->where('h.estado_id', $idCerrada);
    }

    /**
     * Fecha del último paso a `cerrada` por solicitud.
     */
    private function cierres(int $idCerrada): Builder
    {
        return DB::table('historial_estados')
            ->selectRaw('solicitud_id, MAX(created_at) AS cerrada_at')
            ->where('estado_id', $idCerrada)
            ->groupBy('solicitud_id');
    }

    /**
     * @param  array{desde?: string, hasta?: string}  $filtros
     */
    private function solicitudesFiltradas(array $filtros): Builder
    {
        $query = DB::table('solicitudes as s');

        if (isset($filtros['desde'])) {
            $query->whereDate('s.created_at', '>=', $filtros['desde']);
        }

        if (isset($filtros['hasta'])) {
            $query->whereDate('s.created_at', '<=', $filtros['hasta']);
        }

        return $query;
    }

    /**
     * Conteo por catálogo con todas las claves presentes (0 si no hay).
     *
     * @return array<string, int>
     */
    private function conteoPor(string $columna, string $tablaCatalogo): array
    {
        $cuentas = DB::table('solicitudes')
            ->selectRaw("$columna AS id, COUNT(*) AS total")
            ->groupBy($columna)
            ->pluck('total', 'id');

        $resultado = [];
        foreach ($this->catalogo($tablaCatalogo) as $id => $nombre) {
            $resultado[$nombre] = (int) ($cuentas[$id] ?? 0);
        }

        return $resultado;
    }

    /**
     * @return array<int, string> id => nombre, en el orden natural del catálogo
     */
    private function catalogo(string $tabla): array
    {
        // Las prioridades se ordenan por nivel; el resto por id.
        return DB::table($tabla)
            ->orderBy($tabla === 'prioridades' ? 'nivel' : 'id')
            ->pluck('nombre', 'id')
            ->all();
    }

    /**
     * @param  array<int, string>  $estados
     */
    private function idEstado(array $estados, EstadoNombre $estado): int
    {
        return (int) array_search($estado->value, $estados, true);
    }

    private function decimal(mixed $valor): ?float
    {
        return $valor === null ? null : (float) $valor;
    }
}
