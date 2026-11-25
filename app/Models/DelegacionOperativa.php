<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DelegacionOperativa extends Model
{
    protected $table = 'delegaciones_operativas';

    protected $fillable = [
        'titular_id', 'sustituto_id', 'autorizada_por', 'permisos', 'vigente_desde', 'vigente_hasta',
        'motivo', 'revocada_en', 'revocada_por',
    ];

    protected $casts = [
        'permisos' => 'array', 'vigente_desde' => 'datetime', 'vigente_hasta' => 'datetime', 'revocada_en' => 'datetime',
    ];

    public function titular()
    {
        return $this->belongsTo(User::class, 'titular_id');
    }

    public function sustituto()
    {
        return $this->belongsTo(User::class, 'sustituto_id');
    }

    public function autorizador()
    {
        return $this->belongsTo(User::class, 'autorizada_por');
    }

    public function regiones()
    {
        return $this->belongsToMany(Region::class, 'delegacion_region', 'delegacion_id');
    }

    public function bodegas()
    {
        return $this->belongsToMany(Bodega::class, 'delegacion_bodega', 'delegacion_id');
    }
}
