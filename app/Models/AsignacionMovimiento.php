<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionMovimiento extends Model
{
    protected $table = 'asignacion_movimientos';

    protected $fillable = [
        'asignacion_inventario_id',
        'tipo',
        'cantidad',
        'detalle',
        'grupo_devolucion',
        'estado_devolucion',
        'stock_tipo_resultante',
        'vida_util_original_meses',
        'vida_util_consumida_meses',
        'vida_util_restante_meses',
        'bodega_retorno_id',
        'user_id',
    ];

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_inventario_id');
    }

    public function bodegaRetorno()
    {
        return $this->belongsTo(Bodega::class, 'bodega_retorno_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}