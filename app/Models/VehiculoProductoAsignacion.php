<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoProductoAsignacion extends Model
{
    protected $table = 'vehiculo_producto_asignaciones';

    protected $fillable = [
        'asignacion_vehiculo_id',
        'vehiculo_vin',
        'producto_codigo',
        'bodega_id',
        'cantidad',
        'tipo_control',
        'serial',
        'fecha',
        'motivo',
        'observaciones',
        'estado',
        'activa',
        'asignado_por_user_id',
        'cerrado_por_user_id',
        'fecha_cierre',
        'accion_cierre',
        'mal_uso_colaborador',
        'colaborador_responsable_codigo',
        'descuento_generado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_cierre' => 'datetime',
        'activa' => 'boolean',
        'mal_uso_colaborador' => 'boolean',
        'descuento_generado' => 'boolean',
    ];

    public function asignacionVehiculo() { return $this->belongsTo(AsignacionVehiculo::class, 'asignacion_vehiculo_id'); }
    public function vehiculo() { return $this->belongsTo(Vehiculo::class, 'vehiculo_vin', 'vin'); }
    public function producto() { return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo'); }
    public function bodega() { return $this->belongsTo(Bodega::class, 'bodega_id'); }
    public function asignadoPor() { return $this->belongsTo(User::class, 'asignado_por_user_id'); }
    public function cerradoPor() { return $this->belongsTo(User::class, 'cerrado_por_user_id'); }
    public function colaboradorResponsable() { return $this->belongsTo(Colaborador::class, 'colaborador_responsable_codigo', 'codigo'); }

    public function archivosFirmados() { return $this->hasMany(VehiculoProductoArchivo::class, 'vehiculo_producto_asignacion_id'); }
    public function pdfAsignacionFirmado() { return $this->hasOne(VehiculoProductoArchivo::class, 'vehiculo_producto_asignacion_id')->where('tipo_documento', 'asignacion_firmada')->latestOfMany(); }
    public function pdfDevolucionFirmado() { return $this->hasOne(VehiculoProductoArchivo::class, 'vehiculo_producto_asignacion_id')->where('tipo_documento', 'devolucion_firmada')->latestOfMany(); }
}
