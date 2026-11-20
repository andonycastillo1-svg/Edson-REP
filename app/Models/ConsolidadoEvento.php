<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoEvento extends Model
{
    protected $table = 'consolidado_eventos';
    protected $fillable = ['user_id', 'accion', 'nota', 'cambios'];
    protected $casts = ['cambios' => 'array'];
    public function user() { return $this->belongsTo(User::class); }
}
