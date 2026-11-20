<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotoInspeccion extends Model
{
    protected $table = 'inspeccion_fotos';
    public $timestamps = false;
    protected $fillable = ['ruta', 'nombre'];
}
