<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operacion extends Model
{
    protected $table = 'operaciones';

    public const TIPO_TRASLADO = 'TRASLADO';

    public const ESTADO_PENDIENTE = 'PENDIENTE';
    public const ESTADO_APROBADO  = 'APROBADO';
    public const ESTADO_RECHAZADO = 'RECHAZADO';

    protected $fillable = [
        'tipo',
        'estado',
        'bodega_origen_id',
        'bodega_destino_id',
        'creado_por',
        'aprobado_por',
        'aprobado_en',
        'rechazado_por',
        'rechazado_en',
        'motivo_rechazo',
        'observacion',
        'archivo_excel_path',
        'archivo_excel_nombre',
    ];

    protected $casts = [
        'aprobado_en'  => 'datetime',
        'rechazado_en' => 'datetime',
    ];

    // =========================
    // Relaciones
    // =========================

    public function detalles()
    {
        return $this->hasMany(OperacionDetalle::class, 'operacion_id');
    }

    public function bodegaOrigen()
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function rechazador()
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }
}