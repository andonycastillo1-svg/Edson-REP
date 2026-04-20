<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AsignacionInventario extends Model
{
    protected $table = 'asignaciones_inventarios';

    protected $fillable = [
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
        'pdf_firmado',
    ];

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
        return $this->belongsTo(Bodega::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function movimientos()
    {
        return $this->hasMany(AsignacionMovimiento::class, 'asignacion_inventario_id');
    }

    public function estadoHistorial()
    {
        return $this->hasMany(AsignacionEstadoHistorial::class, 'asignacion_inventario_id');
    }
}
