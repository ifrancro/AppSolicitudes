<?php

namespace App\Models;

use Database\Factories\NotificacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    /** @use HasFactory<NotificacionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'notificaciones';

    protected $fillable = ['usuario_id', 'solicitud_id', 'mensaje', 'leido'];

    protected function casts(): array
    {
        return ['leido' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }
}
