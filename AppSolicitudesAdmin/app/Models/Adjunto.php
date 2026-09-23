<?php

namespace App\Models;

use Database\Factories\AdjuntoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjunto extends Model
{
    /** @use HasFactory<AdjuntoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Tipos admitidos (mime real => extensión con la que se guarda). */
    public const EXTENSIONES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    protected $table = 'adjuntos';

    protected $fillable = ['solicitud_id', 'url_archivo', 'tipo_archivo', 'subido_por'];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
