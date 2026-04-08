<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperacionDetalle extends Model
{
    // 👇 ESTA debe ser la tabla de los detalles (NO operaciones)
    protected $table = 'operacion_detalles';

    // Si tu tabla tiene created_at y updated_at, déjalo así.
    // Si NO los tiene, agrega: public $timestamps = false;
    public $timestamps = true;

    protected $fillable = [
        'operacion_id',
        'producto_codigo',
        'cantidad',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    // Cada detalle pertenece a una operación
    public function operacion()
    {
        return $this->belongsTo(Operacion::class, 'operacion_id');
    }

    // Cada detalle pertenece a un producto
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo');
    }
}