<?php

namespace App\Models;

use Database\Factories\AccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accion extends Model
{
    /** @use HasFactory<AccionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'acciones';

    protected $fillable = ['solicitud_id', 'responsable_id', 'descripcion'];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
