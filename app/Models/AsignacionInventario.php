<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Colaborador;
use App\Models\Producto;
use App\Models\Bodega;

class AsignacionInventario extends Model
{
    protected $table = 'asignaciones_inventarios';

    protected $fillable = [
        'grupo_asignacion',
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

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'cantidad_asignada' => 'integer',
        'costo_unitario' => 'decimal:2',
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
        return $this->belongsTo(Bodega::class, 'bodega_id');
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