<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioLoteOperativo extends Model
{
    protected $table = 'inventario_lotes_operativos';

    public $timestamps = false;

    protected $fillable = [
        'bodega_id', 'producto_codigo', 'stock_tipo', 'cantidad', 'ingresado_en',
        'vida_original_meses', 'vida_restante_segundos', 'valor_referencia', 'retornado_desde_id',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'ingresado_en' => 'datetime',
        'vida_original_meses' => 'integer',
        'vida_restante_segundos' => 'integer',
        'valor_referencia' => 'decimal:2',
    ];
}
