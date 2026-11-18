<?php

namespace App\Sistema;

use Illuminate\Database\Eloquent\Model;

class ReglaRol extends Model
{
    protected $table = 'sistema_permisos_rol';

    protected $casts = ['permitido' => 'boolean'];
}
