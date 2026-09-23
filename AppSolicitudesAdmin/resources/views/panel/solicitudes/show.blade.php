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

    <div class="grid grid--2">
        @can('classify', $solicitud)
            <section class="card">
                <h2>Clasificar</h2>
                <form method="POST" action="{{ route('panel.solicitudes.clasificar', $solicitud) }}" class="filters">
                    @csrf
                    <label>Tipo
                        <select name="tipo_id">
                            @foreach ($catalogos['tipos'] as $tipo)
                                <option value="{{ $tipo->id }}" @selected(old('tipo_id', $solicitud->tipo_id) == $tipo->id)>{{ str_replace('_', ' ', $tipo->nombre) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Prioridad
                        <select name="prioridad_id">
                            @foreach ($catalogos['prioridades'] as $prioridad)
                                <option value="{{ $prioridad->id }}" @selected(old('prioridad_id', $solicitud->prioridad_id) == $prioridad->id)>{{ $prioridad->nombre }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit">Guardar</button>
                </form>
            </section>
        @endcan

        @can('assign', $solicitud)
            <section class="card">
                <h2>{{ $solicitud->asignacionActiva ? 'Reasignar' : 'Asignar' }} responsable</h2>
                <form method="POST" action="{{ route('panel.solicitudes.asignar', $solicitud) }}">
                    @csrf
                    <label>Responsable
                        <select name="responsable_id" required>
                            <option value="">Selecciona…</option>
                            @foreach ($responsables as $responsable)
                                <option value="{{ $responsable->id }}">{{ $responsable->nombre }} ({{ $responsable->asignaciones_activas }} activas)</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="margin-top:8px">Comentario (opcional)
                        <textarea name="comentario" maxlength="500">{{ old('comentario') }}</textarea>
                    </label>
                    <p><button type="submit">Asignar</button></p>
                </form>
            </section>
        @endcan

        @if (count($destinos) > 0)
            <section class="card">
                <h2>Cambiar estado</h2>
                <form method="POST" action="{{ route('panel.solicitudes.estado', $solicitud) }}">
                    @csrf
                    <label>Nuevo estado
                        <select name="estado_id" required>
                            @foreach ($destinos as $destino)
                                @php($estadoDestino = $catalogos['estados']->firstWhere('nombre', $destino->value))
                                <option value="{{ $estadoDestino->id }}">{{ str_replace('_', ' ', $destino->value) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label style="margin-top:8px">Comentario (obligatorio al cancelar)
                        <textarea name="comentario" maxlength="500">{{ old('comentario') }}</textarea>
                    </label>
                    <p><button type="submit">Actualizar estado</button></p>
                </form>
            </section>
        @endif

        @can('createAction', $solicitud)
            <section class="card">
                <h2>Registrar acción realizada</h2>
                <form method="POST" action="{{ route('panel.solicitudes.acciones', $solicitud) }}">
                    @csrf
                    <textarea name="descripcion" maxlength="1000" required placeholder="Describe lo que hiciste…">{{ old('descripcion') }}</textarea>
                    <p><button type="submit">Registrar</button></p>
                </form>
            </section>
        @endcan
    </div>

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
                    <li><a href="{{ route('panel.adjuntos.descargar', $adjunto) }}" target="_blank" rel="noopener">{{ basename($adjunto->url_archivo) }}</a> <span class="muted">({{ $adjunto->tipo_archivo }}, subido por {{ $adjunto->autor->nombre }})</span></li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
