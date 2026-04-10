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
        'user_id',
    ];

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_inventario_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
