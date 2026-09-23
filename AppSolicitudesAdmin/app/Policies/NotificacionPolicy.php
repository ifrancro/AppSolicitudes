<?php

namespace App\Policies;

use App\Models\Notificacion;
use App\Models\User;

class NotificacionPolicy
{
    /** Cada usuario marca solo sus propias notificaciones. */
    public function markAsRead(User $user, Notificacion $notificacion): bool
    {
        return $notificacion->usuario_id === $user->id;
    }
}
