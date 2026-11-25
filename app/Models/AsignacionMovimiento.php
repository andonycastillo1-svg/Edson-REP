<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AsignacionMovimiento extends Model
{
    public const DEVOLUCION_COMPLETA = 'custodia_total';

    protected $table = 'asignacion_movimientos';

    protected $fillable = [
        'asignacion_inventario_id',
        'tipo',
        'cantidad',
        'detalle',
        'grupo_devolucion',
        'estado_devolucion',
        'stock_tipo_resultante',
        'bodega_retorno_id',
        'user_id',
        'motivo_administrativo',
        'ocurrio_en',
        'vida_restante_segundos',
    ];

    protected $casts = ['ocurrio_en' => 'datetime', 'vida_restante_segundos' => 'integer'];

    public function scopeConHojaDevolucion(Builder $consulta): Builder
    {
        return $consulta->whereIn('tipo', ['Devolucion', 'Reemplazo'])
            ->where(fn ($q) => $q->whereNull('motivo_administrativo')
                ->orWhereIn('motivo_administrativo', ['renuncia', 'despido', self::DEVOLUCION_COMPLETA]));
    }

    public function getCondicionDevolucionAttribute(): string
    {
        return match ($this->estado_devolucion) {
            'buen_estado' => 'Recibido en buen estado',
            'danado' => 'Recibido dañado',
            'sin_retorno' => 'No devuelto',
            default => \App\Services\ResolverCustodia::CONDICIONES[$this->estado_devolucion] ?? ($this->detalle ?: 'Retorno físico'),
        };
    }

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_inventario_id');
    }

    public function bodegaRetorno()
    {
        return $this->belongsTo(Bodega::class, 'bodega_retorno_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
