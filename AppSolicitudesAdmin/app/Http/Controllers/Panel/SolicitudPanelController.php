<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListadoSolicitudesRequest;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Solicitud;
use App\Models\TipoSolicitud;
use Illuminate\View\View;

class SolicitudPanelController extends Controller
{
    private const RELACIONES_LISTADO = ['tipo', 'prioridad', 'estado', 'estudiante', 'asignacionActiva.responsable'];

    /** Listado global (personal administrativo y administrador). */
    public function index(ListadoSolicitudesRequest $request): View
    {
        $this->authorize('viewAny', Solicitud::class);

        $solicitudes = Solicitud::with(self::RELACIONES_LISTADO)
            ->filtrar($request->filtros())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('panel.solicitudes.index', [
            'titulo' => 'Todas las solicitudes',
            'solicitudes' => $solicitudes,
            'filtros' => $request->filtros(),
            'catalogos' => $this->catalogos(),
            'conFiltroResponsable' => true,
        ]);
    }

    /** Solicitudes con asignación activa al usuario (responsable). */
    public function asignadas(ListadoSolicitudesRequest $request): View
    {
        $this->authorize('viewAssigned', Solicitud::class);

        $filtros = $request->filtros();
        $filtros['responsable_id'] = $request->user()->id;

        $solicitudes = Solicitud::with(self::RELACIONES_LISTADO)
            ->filtrar($filtros)
            ->paginate($request->perPage())
            ->withQueryString();

        return view('panel.solicitudes.index', [
            'titulo' => 'Mis solicitudes asignadas',
            'solicitudes' => $solicitudes,
            'filtros' => $request->filtros(),
            'catalogos' => $this->catalogos(),
            'conFiltroResponsable' => false,
        ]);
    }

    public function show(Solicitud $solicitud): View
    {
        $this->authorize('view', $solicitud);

        $solicitud->load([
            'tipo', 'prioridad', 'estado', 'estudiante', 'asignacionActiva.responsable',
            'historialEstados.estado', 'historialEstados.autor',
            'asignaciones.responsable', 'asignaciones.asignadoPor',
            'acciones.responsable', 'adjuntos.autor',
        ]);

        return view('panel.solicitudes.show', ['solicitud' => $solicitud]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'estados' => EstadoSolicitud::orderBy('id')->get(),
            'tipos' => TipoSolicitud::orderBy('id')->get(),
            'prioridades' => Prioridad::orderBy('nivel')->get(),
        ];
    }
}
