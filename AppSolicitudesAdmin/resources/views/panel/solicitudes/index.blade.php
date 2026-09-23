@extends('layouts.panel')

@section('titulo', $titulo)

@section('contenido')
    <h1>{{ $titulo }}</h1>

    <div class="card">
        <form class="filters" method="GET">
            <label>
                Búsqueda
                <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Título…">
            </label>
            <label>
                Estado
                <select name="estado_id">
                    <option value="">Todos</option>
                    @foreach ($catalogos['estados'] as $estado)
                        <option value="{{ $estado->id }}" @selected(($filtros['estado_id'] ?? null) == $estado->id)>{{ str_replace('_', ' ', $estado->nombre) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Tipo
                <select name="tipo_id">
                    <option value="">Todos</option>
                    @foreach ($catalogos['tipos'] as $tipo)
                        <option value="{{ $tipo->id }}" @selected(($filtros['tipo_id'] ?? null) == $tipo->id)>{{ str_replace('_', ' ', $tipo->nombre) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Prioridad
                <select name="prioridad_id">
                    <option value="">Todas</option>
                    @foreach ($catalogos['prioridades'] as $prioridad)
                        <option value="{{ $prioridad->id }}" @selected(($filtros['prioridad_id'] ?? null) == $prioridad->id)>{{ $prioridad->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Desde
                <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}">
            </label>
            <label>
                Hasta
                <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}">
            </label>
            @if ($conFiltroResponsable)
                <label>
                    <span>Asignación</span>
                    <select name="sin_asignar">
                        <option value="">Todas</option>
                        <option value="1" @selected(! empty($filtros['sin_asignar']))>Sin asignar</option>
                    </select>
                </label>
            @endif
            <button type="submit">Filtrar</button>
            <a class="btn secondary" href="{{ url()->current() }}">Limpiar</a>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Estudiante</th>
                    <th>Responsable</th>
                    <th>Creada</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($solicitudes as $solicitud)
                    <tr>
                        <td>{{ $solicitud->id }}</td>
                        <td><a href="{{ route('panel.solicitudes.show', $solicitud) }}">{{ $solicitud->titulo }}</a></td>
                        <td>{{ str_replace('_', ' ', $solicitud->tipo->nombre) }}</td>
                        <td><span class="badge badge--{{ $solicitud->prioridad->nombre }}">{{ $solicitud->prioridad->nombre }}</span></td>
                        <td><span class="badge badge--{{ $solicitud->estado->nombre }}">{{ str_replace('_', ' ', $solicitud->estado->nombre) }}</span></td>
                        <td>{{ $solicitud->estudiante->nombre }}</td>
                        <td>{{ $solicitud->asignacionActiva?->responsable->nombre ?? '—' }}</td>
                        <td>{{ $solicitud->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No hay solicitudes con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span class="muted">{{ $solicitudes->total() }} resultado(s)</span>
            <span>
                @if ($solicitudes->previousPageUrl())<a href="{{ $solicitudes->previousPageUrl() }}">← Anterior</a>@endif
                @if ($solicitudes->hasPages()) · Página {{ $solicitudes->currentPage() }} de {{ $solicitudes->lastPage() }} · @endif
                @if ($solicitudes->nextPageUrl())<a href="{{ $solicitudes->nextPageUrl() }}">Siguiente →</a>@endif
            </span>
        </div>
    </div>
@endsection
