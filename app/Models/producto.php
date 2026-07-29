<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'unidad_medida', 'vida_util_meses', 'categoria'];

    public function existencias()
    {
        return $this->hasMany(InventarioExistencia::class, 'producto_codigo', 'codigo');
    }
}
