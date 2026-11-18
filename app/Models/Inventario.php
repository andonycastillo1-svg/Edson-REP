<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $fillable = ['producto_codigo', 'bodega_id', 'cantidad'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }
}
