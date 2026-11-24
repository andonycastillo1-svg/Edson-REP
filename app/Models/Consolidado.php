<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consolidado extends Model
{
    public const ESTADOS = [
        'borrador' => 'Borrador', 'enviado' => 'Enviado a coordinación', 'revision' => 'En revisión',
        'aprobado' => 'Aprobado', 'correccion' => 'Devuelto para corrección', 'cerrado' => 'Compras cerradas',
    ];

    protected $table = 'consolidados';

    protected $fillable = ['coordinador_id', 'operador_id', 'nombre', 'desde', 'hasta', 'estado', 'aprobado_por', 'aprobado_en'];

    protected $casts = ['desde' => 'date', 'hasta' => 'date', 'aprobado_en' => 'datetime', 'plan_compra' => 'array', 'detalle_individual' => 'boolean'];

    public function coordinador() { return $this->belongsTo(User::class); }
    public function aprobador() { return $this->belongsTo(User::class, 'aprobado_por'); }
    public function operador() { return $this->belongsTo(User::class); }

    public function corte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CorteRequerimiento::class, 'corte_id');
    }

    public function inspecciones()
    {
        return $this->belongsToMany(Inspeccion::class, 'consolidado_inspeccion');
    }

    public function lineas()
    {
        return $this->hasMany(ConsolidadoLinea::class);
    }

    public function participantes()
    {
        return $this->hasMany(ConsolidadoColaborador::class);
    }

    public function eventos()
    {
        return $this->hasMany(ConsolidadoEvento::class);
    }

    public function constancias()
    {
        return $this->belongsToMany(ConstanciaGerencia::class, 'consolidado_constancia', relatedPivotKey: 'constancia_id');
    }
}
