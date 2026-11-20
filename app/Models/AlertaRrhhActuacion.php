<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaRrhhActuacion extends Model
{
    protected $table = 'alerta_rrhh_actuaciones';

    protected $fillable = ['usuario_id', 'estado', 'nota'];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
