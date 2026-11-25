<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{
    protected $table = 'colaboradores';

    // PK = codigo
    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'codigo',
        'nombre',
        'puesto', 'region_id',
        'estado',
        'created_at',
        'updated_at',
    ];
    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionInventario::class, 'colaborador_codigo');
    }
}
