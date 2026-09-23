<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListadoNotificacionesRequest;
use App\Http\Resources\NotificacionResource;
use App\Models\Notificacion;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificacionController extends Controller
{
    public function index(ListadoNotificacionesRequest $request): AnonymousResourceCollection
    {
        $usuario = $request->user();
        $propias = Notificacion::query()->where('usuario_id', $usuario->id);

        $notificaciones = (clone $propias)
            ->when($request->has('leido'), fn ($q) => $q->where('leido', $request->boolean('leido')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        // El total de no leídas ignora el filtro y la página (contrato §5.7).
        return NotificacionResource::collection($notificaciones)->additional([
            'meta' => ['no_leidas' => (clone $propias)->where('leido', false)->count()],
        ]);
    }

    public function leer(Notificacion $notificacion): NotificacionResource
    {
        $this->authorize('markAsRead', $notificacion);

        // Idempotente: solo escribe si aún no estaba leída.
        if (! $notificacion->leido) {
            $notificacion->update(['leido' => true]);
        }

        return new NotificacionResource($notificacion);
    }
}
