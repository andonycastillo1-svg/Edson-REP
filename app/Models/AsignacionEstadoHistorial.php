<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionEstadoHistorial extends Model
{
    protected $table = 'asignacion_estado_historiales';

    protected $fillable = [
        'asignacion_inventario_id',
        'estado',
        'fecha_evento',
        'detalle',
        'user_id',
    ];

    protected $casts = [
        'fecha_evento' => 'datetime',
    ];
}
