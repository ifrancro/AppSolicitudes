<?php

namespace App\Models;

use Database\Factories\AsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    /** @use HasFactory<AsignacionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'asignaciones';

    protected $fillable = [
        'solicitud_id', 'responsable_id', 'asignado_por',
        'activo', 'fecha_asignacion', 'fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_asignacion' => 'datetime',
            'fecha_fin' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class, 'solicitud_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }
}
