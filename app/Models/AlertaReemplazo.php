<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaReemplazo extends Model
{
    protected $table = 'alertas_reemplazos_rrhh';

    protected $fillable = [
        'colaborador_codigo',
        'producto_codigo',
        'asignacion_anterior_id',
        'asignacion_nueva_id',
        'fecha_asignacion_anterior',
        'fecha_dano_reemplazo',
        'vida_util_meses',
        'meses_restantes',
        'descuento_aplicable',
        'estado',
        'detalle',
    ];

    protected $casts = [
        'fecha_asignacion_anterior' => 'datetime',
        'fecha_dano_reemplazo' => 'datetime',
        'descuento_aplicable' => 'boolean',
    ];
}
