<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoSolicitud extends Model
{
    protected $table = 'tipos_solicitud';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion'];
}
