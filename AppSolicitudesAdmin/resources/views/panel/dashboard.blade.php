@extends('layouts.panel')

@section('titulo', 'Dashboard')

@section('contenido')
    <h1>Dashboard</h1>

    <div class="stats" style="margin-bottom:16px">
        <div class="stat"><div class="stat__value">{{ $resumen['total'] }}</div><div class="stat__label">Solicitudes</div></div>
        <div class="stat"><div class="stat__value">{{ $resumen['sin_asignar'] }}</div><div class="stat__label">Pendientes sin asignar</div></div>
        <div class="stat"><div class="stat__value">{{ $resumen['cerradas_ultimos_30_dias'] }}</div><div class="stat__label">Cerradas (30 días)</div></div>
        <div class="stat">
            <div class="stat__value">{{ $resumen['tiempo_promedio_atencion_horas'] ?? '—' }}</div>
            <div class="stat__label">Horas promedio de atención</div>
        </div>
    </div>

    <div class="grid grid--2">
        <section class="card">
            <h2>Por estado</h2>
            <table>
                <tbody>
                @foreach ($resumen['por_estado'] as $nombre => $total)
                    <tr><td><span class="badge badge--{{ $nombre }}">{{ str_replace('_', ' ', $nombre) }}</span></td><td>{{ $total }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
        <section class="card">
            <h2>Por prioridad</h2>
            <table>
                <tbody>
                @foreach ($resumen['por_prioridad'] as $nombre => $total)
                    <tr><td><span class="badge badge--{{ $nombre }}">{{ $nombre }}</span></td><td>{{ $total }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <section class="card">
        <h2>Reportes por rango de fechas</h2>
        <form class="filters" method="GET">
            <label>Desde <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}"></label>
            <label>Hasta <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}"></label>
            <button type="submit">Aplicar</button>
            <a class="btn secondary" href="{{ route('panel.dashboard') }}">Limpiar</a>
        </form>
    </section>

    <section class="card">
        <h2>Solicitudes por tipo</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tipo</th><th>Total</th>
                    @foreach (array_keys($porTipo[0]['por_estado'] ?? []) as $estado)<th>{{ str_replace('_', ' ', $estado) }}</th>@endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($porTipo as $fila)
                    <tr>
                        <td>{{ str_replace('_', ' ', $fila['tipo']) }}</td>
                        <td>{{ $fila['total'] }}</td>
                        @foreach ($fila['por_estado'] as $total)<td>{{ $total }}</td>@endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Tiempos de atención</h2>
        <p class="muted">Desde la creación hasta el cierre, en horas. Solo solicitudes cerradas: {{ $tiempos['total_cerradas'] }}.</p>
        <dl class="meta">
            <dt>Promedio</dt><dd>{{ $tiempos['promedio_horas'] ?? '—' }}</dd>
            <dt>Mínimo</dt><dd>{{ $tiempos['minimo_horas'] ?? '—' }}</dd>
            <dt>Máximo</dt><dd>{{ $tiempos['maximo_horas'] ?? '—' }}</dd>
        </dl>
        <div class="table-wrap" style="margin-top:12px">
            <table>
                <thead><tr><th>Tipo</th><th>Cerradas</th><th>Promedio (h)</th></tr></thead>
                <tbody>
                @foreach ($tiempos['por_tipo'] as $fila)
                    <tr><td>{{ str_replace('_', ' ', $fila['tipo']) }}</td><td>{{ $fila['total_cerradas'] }}</td><td>{{ $fila['promedio_horas'] ?? '—' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
