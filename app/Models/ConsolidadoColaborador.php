<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoColaborador extends Model
{
    protected $table = 'consolidado_colaboradores';

    public $timestamps = false;

    protected $fillable = ['colaborador_codigo', 'region_id', 'agregado_por'];

    public function consolidado() { return $this->belongsTo(Consolidado::class); }

    public function colaborador() { return $this->belongsTo(Colaborador::class, 'colaborador_codigo'); }

    public function region() { return $this->belongsTo(Region::class); }

    public function necesidades() { return $this->hasMany(ConsolidadoNecesidad::class); }
}
