<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoLinea extends Model
{
    protected $table = 'consolidado_lineas';
    public $timestamps = false;
    protected $fillable = ['producto_codigo', 'original', 'cantidad'];
    public function producto() { return $this->belongsTo(Producto::class, 'producto_codigo'); }
}
