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

    public function bajasLaborales()
    {
        return $this->hasMany(BajaLaboral::class, 'colaborador_codigo');
    }

    public function bajaVigente()
    {
        return $this->hasOne(BajaLaboral::class, 'colaborador_codigo')
            ->whereNull('cerrada_en')->whereIn('motivo', array_keys(BajaLaboral::MOTIVOS))
            ->whereDate('fecha_efectiva', '<=', today());
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionInventario::class, 'colaborador_codigo');
    }
}
