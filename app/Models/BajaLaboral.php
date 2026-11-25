<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BajaLaboral extends Model
{
    public const MOTIVOS = ['renuncia' => 'Renuncia', 'despido' => 'Despido'];

    protected $table = 'bajas_laborales';

    protected $fillable = ['motivo', 'fecha_efectiva', 'observaciones', 'registrada_por'];

    protected $casts = ['fecha_efectiva' => 'date', 'cerrada_en' => 'datetime'];

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }
}
