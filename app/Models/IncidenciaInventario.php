<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidenciaInventario extends Model
{
    public const MOTIVOS = [
        'danado' => 'Daño',
        'perdido' => 'Pérdida',
        'robado' => 'Robo',
        'otra' => 'Otra incidencia',
        'baja' => 'Baja',
    ];

    protected $table = 'incidencias_inventario';

    protected $fillable = [
        'baja_laboral_id',
        'asignacion_id', 'reemplazo_id', 'registrado_por', 'cantidad', 'motivo',
        'resultado_stock', 'ocurrio_en', 'observaciones', 'estado_vida',
        'vida_restante_segundos', 'archivo', 'archivo_nombre', 'archivo_subido_por',
        'archivo_subido_en',
    ];

    protected $casts = [
        'ocurrio_en' => 'datetime',
        'archivo_subido_en' => 'datetime',
        'vida_restante_segundos' => 'integer',
    ];

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function archivoSubidoPor()
    {
        return $this->belongsTo(User::class, 'archivo_subido_por');
    }

    public function alerta()
    {
        return $this->hasOne(AlertaReemplazo::class, 'incidencia_inventario_id');
    }

    public function getMotivoEtiquetaAttribute(): string
    {
        return self::MOTIVOS[$this->motivo] ?? $this->motivo;
    }
}
