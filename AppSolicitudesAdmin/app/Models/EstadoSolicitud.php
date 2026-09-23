<?php

namespace App\Models;

use App\Enums\EstadoNombre;
use Illuminate\Database\Eloquent\Model;

class EstadoSolicitud extends Model
{
    protected $table = 'estados_solicitud';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public static function idDe(EstadoNombre $estado): int
    {
        return static::query()->where('nombre', $estado->value)->firstOrFail()->id;
    }
}
