<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AsignacionInventario extends Model
{
    protected $table = 'asignaciones_inventarios';

    protected $fillable = [
        'grupo_asignacion',
        'tipo_entrega',
        'asignacion_anterior_id',
        'colaborador_codigo',
        'producto_codigo',
        'bodega_id',
        'user_id',
        'cantidad_asignada',
        'fecha',
        'costo_unitario',
        'aprobado_por',
        'medio_solicitud',
        'imagen',
        'observaciones',
        'fecha_vencimiento',
        'estado',
        'stock_tipo',
        'vida_util_original_meses',
        'vida_util_restante_meses',
        'vida_util_disponible_segundos',
        'estado_evidencia',
        'pdf_firmado',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'vida_util_disponible_segundos' => 'integer',
        'cantidad_asignada' => 'integer',
        'costo_unitario' => 'decimal:2',
    ];

    public function scopeCustodiaActiva(Builder $consulta): Builder
    {
        return $consulta->where('estado', 'Activa')->where('cantidad_asignada', '>', 0);
    }

    public function entrega()
    {
        return $this->belongsTo(EntregaInventario::class, 'grupo_asignacion');
    }

    public function incidencias()
    {
        return $this->hasMany(IncidenciaInventario::class, 'asignacion_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_codigo', 'codigo');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Relación histórica conservada para compatibilidad. */
    public function archivosFirmados()
    {
        return $this->hasMany(AsignacionInventarioArchivo::class, 'asignacion_inventario_id');
    }

    public function evidencias()
    {
        return $this->hasMany(AsignacionInventarioArchivo::class, 'asignacion_inventario_id')
            ->whereIn('tipo_documento', ['asignacion_firmada', 'evidencia_entrega']);
    }

    public function pdfsAsignacionFirmados()
    {
        return $this->hasMany(AsignacionInventarioArchivo::class, 'asignacion_inventario_id')
            ->where('tipo_documento', 'asignacion_firmada');
    }

    public function imagenesEntrega()
    {
        return $this->hasMany(AsignacionInventarioArchivo::class, 'asignacion_inventario_id')
            ->where('tipo_documento', 'evidencia_entrega');
    }

    public function pdfAsignacionFirmado()
    {
        return $this->hasOne(AsignacionInventarioArchivo::class, 'asignacion_inventario_id')
            ->where('tipo_documento', 'asignacion_firmada')
            ->latestOfMany();
    }

    public function movimientos()
    {
        return $this->hasMany(AsignacionMovimiento::class, 'asignacion_inventario_id');
    }

    public function estadoHistorial()
    {
        return $this->hasMany(AsignacionEstadoHistorial::class, 'asignacion_inventario_id');
    }
    public function periodosActivos()
    {
        return $this->periodos()->whereNull('devuelto_en')->orderBy('id');
    }

    public function periodos()
    {
        return $this->hasMany(AsignacionPeriodo::class, 'asignacion_inventario_id');
    }

    public function movimientoInicial()
    {
        return $this->hasOne(AsignacionMovimiento::class, 'asignacion_inventario_id')
            ->ofMany(['id' => 'min'], fn ($query) => $query->where('tipo', 'Asignacion'));
    }
}
