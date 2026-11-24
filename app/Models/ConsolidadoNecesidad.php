<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsolidadoNecesidad extends Model
{
    protected $table = 'consolidado_necesidades';

    public $timestamps = false;

    protected $fillable = ['producto_codigo', 'original', 'cantidad'];

    protected $casts = ['original' => 'integer', 'cantidad' => 'integer'];

    public function participante() { return $this->belongsTo(ConsolidadoColaborador::class, 'consolidado_colaborador_id'); }

    public function producto() { return $this->belongsTo(Producto::class, 'producto_codigo'); }

    public function asignaciones() { return $this->hasMany(AsignacionInventario::class, 'requerimiento_id'); }
}
