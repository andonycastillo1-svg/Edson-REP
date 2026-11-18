<?php

namespace App\Sistema;

use Illuminate\Database\Eloquent\Model;

class ReglaUsuario extends Model
{
    protected $table = 'sistema_permisos_usuario';

    protected $casts = ['permitido' => 'boolean'];
}
