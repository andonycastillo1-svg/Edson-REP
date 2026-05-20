<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaReemplazo extends Model
{
    protected $table = 'alertas_reemplazos_rrhh';

    protected $fillable = [
        'colaborador_codigo',
        'producto_codigo',
        'producto_nombre',
        'asignacion_anterior_id',
        'asignacion_nueva_id',
        'fecha_asignacion_anterior',
        'fecha_dano_reemplazo',
        'vida_util_meses',
        'meses_restantes',
        'meses_usados',
        'costo_producto',
        'descuento_proporcional_sugerido',
        'motivo_alerta',
        'descuento_aplicable',
        'estado',
        'detalle',
    ];

    protected $casts = [
        'fecha_asignacion_anterior' => 'datetime',
        'fecha_dano_reemplazo' => 'datetime',
        'descuento_aplicable' => 'boolean',
        'costo_producto' => 'decimal:2',
        'descuento_proporcional_sugerido' => 'decimal:2',
    ];
}
