<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionPeriodo extends Model
{
    protected $table = 'asignacion_periodos';
    protected $guarded = [];

    protected $casts = [
        'asignado_en' => 'datetime',
        'devuelto_en' => 'datetime',
        'reutilizable' => 'boolean',
        'evidencia_calculo' => 'array',
        'valor_referencia' => 'decimal:2',
        'cobro_calculado' => 'decimal:2',
    ];

    public function asignacion() { return $this->belongsTo(AsignacionInventario::class, 'asignacion_inventario_id'); }
    public function existencia() { return $this->belongsTo(InventarioExistencia::class, 'inventario_existencia_id'); }
    public function bodegaRetorno() { return $this->belongsTo(Bodega::class, 'bodega_retorno_id'); }
}
