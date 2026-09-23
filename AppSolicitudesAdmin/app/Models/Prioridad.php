<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prioridad extends Model
{
    protected $table = 'prioridades';

    public $timestamps = false;

    protected $fillable = ['nombre', 'nivel'];

    protected function casts(): array
    {
        return ['nivel' => 'integer'];
    }
}
