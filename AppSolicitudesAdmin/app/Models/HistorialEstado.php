<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstado extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historial_estados';

    protected $fillable = ['solicitud_id', 'estado_id', 'cambiado_por', 'comentario'];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoSolicitud::class, 'estado_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cambiado_por');
    }
}
