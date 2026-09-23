<?php

namespace App\Policies;

use App\Models\Adjunto;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Un adjunto se ve y se descarga solo si el usuario puede ver su solicitud.
 */
class AdjuntoPolicy
{
    public function view(User $user, Adjunto $adjunto): bool
    {
        return Gate::forUser($user)->allows('view', $adjunto->solicitud);
    }
}
