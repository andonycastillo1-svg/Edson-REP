<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcesoInspeccion extends Model
{
    protected $table = 'procesos_inspeccion';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
