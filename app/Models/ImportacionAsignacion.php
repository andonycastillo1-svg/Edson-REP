<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportacionAsignacion extends Model
{
    protected $table = 'importaciones_asignacion';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'bodega_id', 'consolidado_id', 'filas', 'entregas', 'huella_confirmada', 'huella_operativa', 'confirmada_en'];
    protected $casts = ['filas' => 'array', 'entregas' => 'array', 'confirmada_en' => 'datetime'];

    public function consolidado()
    {
        return $this->belongsTo(Consolidado::class);
    }
}
