<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoAsignacion extends Model
{
    protected $table = 'motivos_asignacion';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'politica', 'activo'];

    public const POLITICAS = [
        'sin_propuesta' => 'Registrar el motivo',
        'revision' => 'Remitir a RR. HH.',
        'proporcional' => 'Remitir a RR. HH. con referencia proporcional si hay datos',
    ];
}
