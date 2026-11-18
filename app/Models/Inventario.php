<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $fillable = ['producto_codigo', 'bodega_id', 'cantidad'];

    public function scopeDisponiblesAgrupados(Builder $query): Builder
    {
        return $query->whereIn('stock_tipo', ['nuevo', 'usado'])->where('cantidad', '>', 0)
            ->select('producto_codigo', 'bodega_id')->selectRaw('SUM(cantidad) as cantidad')
            ->groupBy('producto_codigo', 'bodega_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }
}
