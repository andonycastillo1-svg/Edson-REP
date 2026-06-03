<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionInventarioArchivo extends Model
{
    protected $table = 'asignacion_inventario_archivos';

    protected $fillable = [
        'asignacion_inventario_id',
        'grupo_devolucion',
        'tipo_documento',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'subido_por_user_id',
    ];

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_inventario_id');
    }

    public function subidoPor()
    {
        return $this->belongsTo(User::class, 'subido_por_user_id');
    }
}
