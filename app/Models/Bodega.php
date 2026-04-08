<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $table = 'bodegas';

    protected $fillable = [
        'nombre',
        'ubicacion',
        'tipo',
    ];

    public function inventarios()
    {
        return $this->hasMany(\App\Models\Inventario::class, 'bodega_id');
    }

    public function movimientosOrigen()
    {
        return $this->hasMany(\App\Models\Movimiento::class, 'bodega_origen_id');
    }

    public function movimientosDestino()
    {
        return $this->hasMany(\App\Models\Movimiento::class, 'bodega_destino_id');
    }
}
