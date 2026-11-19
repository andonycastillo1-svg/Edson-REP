<?php

namespace App\Sistema;

use Illuminate\Database\Eloquent\Model;

class Campo extends Model
{
    protected $table = 'sistema_campos';

    protected $fillable = [
        'modulo', 'clave', 'etiqueta', 'ayuda', 'tipo', 'predeterminado',
        'permiso_lectura', 'permiso_edicion', 'activo', 'obligatorio', 'formulario',
        'tabla', 'filtro', 'exportacion', 'orden', 'archivado_en',
    ];

    protected $casts = [
        'predeterminado' => 'json', 'activo' => 'boolean', 'obligatorio' => 'boolean',
        'formulario' => 'boolean', 'tabla' => 'boolean', 'filtro' => 'boolean',
        'exportacion' => 'boolean', 'archivado_en' => 'datetime',
    ];

    public function ordenable(): bool
    {
        return !in_array($this->tipo, ['texto_largo', 'multiple', 'archivo'], true);
    }

    public function opciones()
    {
        return $this->hasMany(OpcionCampo::class, 'campo_id')->orderBy('orden')->orderBy('id');
    }
}
