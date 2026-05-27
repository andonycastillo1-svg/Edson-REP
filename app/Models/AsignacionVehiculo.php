<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionVehiculo extends Model
{
    protected $table = 'asignaciones_vehiculos';

    protected $fillable = [
        'vehiculo_vin',
        'colaborador_codigo',
        'asignado_por_user_id',
        'desasignado_por_user_id',
        'fecha_inicio',
        'fecha_fin',
        'estado_inicial_vehiculo',
        'estado_final_vehiculo',
        'observaciones_asignacion',
        'observaciones_desasignacion',
        'activa',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activa' => 'boolean',
    ];

    public function vehiculo() { return $this->belongsTo(Vehiculo::class, 'vehiculo_vin', 'vin'); }
    public function colaborador() { return $this->belongsTo(Colaborador::class, 'colaborador_codigo', 'codigo'); }
    public function productos() { return $this->hasMany(VehiculoProductoAsignacion::class, 'asignacion_vehiculo_id'); }
}
