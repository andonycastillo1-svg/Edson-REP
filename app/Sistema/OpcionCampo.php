<?php

namespace App\Sistema;

use Illuminate\Database\Eloquent\Model;

class OpcionCampo extends Model
{
    public $timestamps = false;
    protected $table = 'sistema_campo_opciones';
    protected $fillable = ['etiqueta', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
