<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioExistencia extends Model
{
    protected $table = 'inventario_existencias';

    protected $guarded = [];

    protected $casts = [
        'vida_util_inicial_segundos' => 'integer',
        'vida_util_restante_segundos' => 'integer',
        'valor_referencia' => 'decimal:2',
        'disponible_desde' => 'datetime',
    ];

    public function producto() { return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo'); }
    public function bodega() { return $this->belongsTo(Bodega::class); }
    public function periodos() { return $this->hasMany(AsignacionPeriodo::class); }
}
