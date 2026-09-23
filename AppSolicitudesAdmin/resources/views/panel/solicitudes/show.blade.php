@extends('layouts.panel')

@section('titulo', 'Solicitud #'.$solicitud->id)

@section('contenido')
    <p><a href="{{ url()->previous() === url()->current() ? route('panel.inicio') : url()->previous() }}">← Volver</a></p>

    <h1>#{{ $solicitud->id }} · {{ $solicitud->titulo }}</h1>

    <div class="grid grid--2">
        <section class="card">
            <h2>Datos</h2>
            <dl class="meta">
                <dt>Estado</dt>
                <dd><span class="badge badge--{{ $solicitud->estado->nombre }}">{{ str_replace('_', ' ', $solicitud->estado->nombre) }}</span></dd>
                <dt>Prioridad</dt>
                <dd><span class="badge badge--{{ $solicitud->prioridad->nombre }}">{{ $solicitud->prioridad->nombre }}</span></dd>
                <dt>Tipo</dt>
                <dd>{{ str_replace('_', ' ', $solicitud->tipo->nombre) }}</dd>
                <dt>Ubicación</dt>
                <dd>{{ $solicitud->ubicacion ?? '—' }}</dd>
                <dt>Estudiante</dt>
                <dd>{{ $solicitud->estudiante->nombre }} <span class="muted">({{ $solicitud->estudiante->email }})</span></dd>
                <dt>Responsable</dt>
                <dd>{{ $solicitud->asignacionActiva?->responsable->nombre ?? 'Sin asignar' }}</dd>
                <dt>Creada</dt>
                <dd>{{ $solicitud->created_at->format('d/m/Y H:i') }}</dd>
            </dl>
            <h2 style="margin-top:16px">Descripción</h2>
            <p style="white-space:pre-line;margin:0">{{ $solicitud->descripcion }}</p>
        </section>

        <section class="card">
            <h2>Historial de estados</h2>
            <ul class="timeline">
                @foreach ($solicitud->historialEstados->sortBy('id') as $cambio)
                    <li>
                        <span class="badge badge--{{ $cambio->estado->nombre }}">{{ str_replace('_', ' ', $cambio->estado->nombre) }}</span>
                        <span class="muted">{{ $cambio->created_at->format('d/m/Y H:i') }} · {{ $cambio->autor->nombre }}</span>
                        @if ($cambio->comentario)<div>{{ $cambio->comentario }}</div>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    @stack('acciones')

    <div class="grid grid--2">
        <section class="card">
            <h2>Asignaciones</h2>
            @forelse ($solicitud->asignaciones->sortByDesc('id') as $asignacion)
                <p style="margin:0 0 8px">
                    <strong>{{ $asignacion->responsable->nombre }}</strong>
                    @if ($asignacion->activo)<span class="badge badge--cerrada">activa</span>@endif
                    <br><span class="muted">
                        Asignada por {{ $asignacion->asignadoPor->nombre }} el {{ $asignacion->fecha_asignacion->format('d/m/Y H:i') }}
                        @if ($asignacion->fecha_fin) · hasta {{ $asignacion->fecha_fin->format('d/m/Y H:i') }} @endif
                    </span>
                </p>
            @empty
                <p class="muted">Sin asignaciones.</p>
            @endforelse
        </section>

        <section class="card">
            <h2>Acciones realizadas</h2>
            @forelse ($solicitud->acciones->sortByDesc('id') as $accion)
                <p style="margin:0 0 8px">{{ $accion->descripcion }}<br>
                    <span class="muted">{{ $accion->responsable->nombre }} · {{ $accion->created_at->format('d/m/Y H:i') }}</span></p>
            @empty
                <p class="muted">Aún no hay acciones registradas.</p>
            @endforelse
        </section>
    </div>

    @if ($solicitud->adjuntos->isNotEmpty())
        <section class="card">
            <h2>Evidencias</h2>
            <ul>
                @foreach ($solicitud->adjuntos as $adjunto)
                    <li>{{ basename($adjunto->url_archivo) }} <span class="muted">({{ $adjunto->tipo_archivo }}, subido por {{ $adjunto->autor->nombre }})</span></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
