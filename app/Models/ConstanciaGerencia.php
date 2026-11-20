<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstanciaGerencia extends Model
{
    protected $table = 'constancias_gerencia';
    protected $fillable = ['user_id', 'medio', 'fecha', 'autorizo', 'referencia', 'plan', 'ruta', 'nombre_archivo'];
    protected $casts = ['fecha' => 'date', 'plan' => 'array'];

    public function consolidados()
    {
        return $this->belongsToMany(Consolidado::class, 'consolidado_constancia', 'constancia_id');
    }
}
