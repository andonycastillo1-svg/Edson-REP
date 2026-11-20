<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspeccionLinea extends Model
{
    protected $table = 'inspeccion_lineas';
    public $timestamps = false;
    protected $fillable = ['producto_codigo', 'etiqueta', 'cantidad'];
}
