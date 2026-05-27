<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoProductoAsignacion extends Model
{
    protected $table = 'vehiculo_producto_asignaciones';

    protected $fillable = [
        'asignacion_vehiculo_id',
        'vehiculo_vin',
        'producto_codigo',
        'bodega_id',
        'cantidad',
        'tipo_control',
        'serial',
        'fecha',
        'asignado_por_user_id',
        'observaciones',
        'activa',
    ];

    protected $casts = ['fecha' => 'date', 'activa' => 'boolean'];

    public function producto() { return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo'); }
    public function bodega() { return $this->belongsTo(Bodega::class, 'bodega_id'); }
}
