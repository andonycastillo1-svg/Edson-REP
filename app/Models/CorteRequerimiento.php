<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorteRequerimiento extends Model
{
    public const ESTADOS = ['borrador' => 'Borrador', 'confirmado' => 'Consolidados generados', 'cancelado' => 'Cancelado'];

    protected $table = 'cortes_requerimientos';

    protected $fillable = ['operador_id', 'nombre', 'desde', 'hasta', 'estado', 'coordinadores_elegidos'];

    protected $casts = ['desde' => 'date', 'hasta' => 'date', 'coordinadores_elegidos' => 'array'];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inspecciones(): BelongsToMany
    {
        return $this->belongsToMany(Inspeccion::class, 'corte_inspeccion', 'corte_id')->withPivot('anterior');
    }

    public function consolidados(): HasMany
    {
        return $this->hasMany(Consolidado::class, 'corte_id');
    }
}
