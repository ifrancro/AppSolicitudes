<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoNombre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AsignarSolicitudRequest;
use App\Http\Requests\Api\CambiarEstadoRequest;
use App\Http\Requests\Api\ClasificarSolicitudRequest;
use App\Http\Requests\Api\RegistrarAccionRequest;
use App\Models\Adjunto;
use App\Models\EstadoSolicitud;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\Solicitudes\AccionService;
use App\Services\Solicitudes\AsignacionService;
use App\Services\Solicitudes\CambioEstadoService;
use App\Services\Solicitudes\ClasificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acciones del panel sobre una solicitud. Reutiliza los mismos FormRequest,
 * servicios y políticas que la API: las reglas de negocio viven en un solo sitio.
 * Cuando un servicio lanza ValidationException, Laravel vuelve al formulario con los errores.
 */
class SolicitudAccionPanelController extends Controller
{
    public function clasificar(ClasificarSolicitudRequest $request, Solicitud $solicitud, ClasificacionService $servicio): RedirectResponse
    {
        $this->authorize('classify', $solicitud);

        $servicio->clasificar($solicitud, $request->validated('tipo_id'), $request->validated('prioridad_id'));

        return $this->volver($solicitud, 'Clasificación actualizada.');
    }

    public function asignar(AsignarSolicitudRequest $request, Solicitud $solicitud, AsignacionService $servicio): RedirectResponse
    {
        $this->authorize('assign', $solicitud);

        $responsable = User::findOrFail($request->integer('responsable_id'));
        $servicio->asignar($solicitud, $responsable, $request->user(), $request->validated('comentario'));

        return $this->volver($solicitud, "Solicitud asignada a {$responsable->nombre}.");
    }

    public function cambiarEstado(CambiarEstadoRequest $request, Solicitud $solicitud, CambioEstadoService $servicio): RedirectResponse
    {
        $this->authorize('changeState', $solicitud);

        $destino = EstadoNombre::from(EstadoSolicitud::findOrFail($request->integer('estado_id'))->nombre);

        // Mismo orden que la API: primero el 422 por destino no permitido, luego el 403 por rol.
        $servicio->validarDestinoManual($destino);
        $this->authorize('changeStateTo', [$solicitud, $destino]);

        $servicio->cambiar($solicitud, $destino, $request->user(), $request->validated('comentario'));

        return $this->volver($solicitud, 'Estado actualizado.');
    }

    public function registrarAccion(RegistrarAccionRequest $request, Solicitud $solicitud, AccionService $servicio): RedirectResponse
    {
        $this->authorize('createAction', $solicitud);

        $servicio->registrar($solicitud, $request->user(), $request->validated('descripcion'));

        return $this->volver($solicitud, 'Acción registrada.');
    }

    /** Descarga de evidencias con la misma política que la API; nunca hay URL pública. */
    public function adjunto(Adjunto $adjunto): StreamedResponse
    {
        $this->authorize('view', $adjunto);

        $disco = Storage::disk('local');
        abort_unless($disco->exists($adjunto->url_archivo), 404);

        return $disco->response($adjunto->url_archivo, basename($adjunto->url_archivo), [
            'Content-Type' => $adjunto->tipo_archivo,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function volver(Solicitud $solicitud, string $mensaje): RedirectResponse
    {
        return redirect()->route('panel.solicitudes.show', $solicitud)->with('exito', $mensaje);
    }
}
