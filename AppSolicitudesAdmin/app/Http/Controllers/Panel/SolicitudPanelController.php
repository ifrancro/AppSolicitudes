<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoNombre;
use App\Enums\RolNombre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListadoSolicitudesRequest;
use App\Models\EstadoSolicitud;
use App\Models\Prioridad;
use App\Models\Solicitud;
use App\Models\TipoSolicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
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

        return view('panel.solicitudes.show', [
            'solicitud' => $solicitud,
            'catalogos' => $this->catalogos(),
            'responsables' => Gate::allows('assign', $solicitud) ? $this->responsables() : new Collection,
            'destinos' => $this->destinosPermitidos($solicitud),
        ]);
    }

    /**
     * Estados a los que el usuario puede llevar la solicitud desde aquí: solo los
     * que el contrato permite por vía manual, que son legales desde el estado
     * actual y que autoriza la política.
     *
     * @return list<EstadoNombre>
     */
    private function destinosPermitidos(Solicitud $solicitud): array
    {
        $actual = EstadoNombre::from($solicitud->estado->nombre);

        return array_values(array_filter(
            [EstadoNombre::EnProceso, EstadoNombre::Cerrada, EstadoNombre::Cancelada],
            fn (EstadoNombre $destino) => $actual->puedePasarA($destino)
                && Gate::allows('changeStateTo', [$solicitud, $destino]),
        ));
    }

    /**
     * Responsables activos con su carga, para el selector de asignación.
     *
     * @return Collection<int, User>
     */
    private function responsables(): Collection
    {
        return User::query()
            ->whereHas('rol', fn ($q) => $q->where('nombre', RolNombre::Responsable->value))
            ->where('activo', true)
            ->withCount(['asignaciones as asignaciones_activas' => fn ($q) => $q->where('activo', true)])
            ->orderBy('asignaciones_activas')
            ->orderBy('nombre')
            ->get();
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
