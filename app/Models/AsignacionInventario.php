<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionInventario extends Model
{
    protected $table = 'asignaciones_inventarios';

    protected $fillable = [
        'colaborador_codigo',
        'producto_codigo',
        'bodega_id',
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
}