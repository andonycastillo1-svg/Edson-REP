<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehiculoProductoArchivo extends Model
{
    protected $table = 'vehiculo_producto_archivos';

    protected $fillable = [
        'vehiculo_producto_asignacion_id',
        'tipo_documento',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'subido_por_user_id',
    ];

    public function asignacion()
    {
        return $this->belongsTo(VehiculoProductoAsignacion::class, 'vehiculo_producto_asignacion_id');
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}
