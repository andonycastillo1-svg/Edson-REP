<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionVehiculoArchivo extends Model
{
    protected $table = 'asignacion_vehiculo_archivos';

    protected $fillable = [
        'asignacion_vehiculo_id',
        'tipo_documento',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'subido_por_user_id',
    ];

    public function asignacion()
    {
        return $this->belongsTo(AsignacionVehiculo::class, 'asignacion_vehiculo_id');
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}