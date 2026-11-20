<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaReemplazo extends Model
{
    public const RELACIONES_LISTADO = ['colaborador', 'producto', 'registrador', 'incidencia.asignacion.bodega',
        'asignacionAnterior.bodega', 'refaccion.bodega'];

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_EN_REVISION = 'en_revision';
    public const ESTADO_FINALIZADO = 'finalizado';

    protected $table = 'alertas_reemplazos_rrhh';

    protected $fillable = [
        'incidencia_inventario_id',
        'colaborador_codigo',
        'producto_codigo',
        'vehiculo_vin',
        'vehiculo_producto_asignacion_id',
        'cantidad',
        'costo_estimado',
        'registrado_por_user_id',
        'asignacion_anterior_id',
        'asignacion_nueva_id',
        'fecha_asignacion_anterior',
        'fecha_dano_reemplazo',
        'vida_util_meses',
        'meses_restantes',
        'descuento_aplicable',
        'estado',
        'detalle',
    ];

    protected $casts = [
        'fecha_asignacion_anterior' => 'datetime',
        'fecha_dano_reemplazo' => 'datetime',
        'descuento_aplicable' => 'boolean',
    ];

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente de revisión',
        self::ESTADO_EN_REVISION => 'En revisión',
        self::ESTADO_FINALIZADO => 'Finalizada',
    ];

    public function incidencia()
    {
        return $this->belongsTo(IncidenciaInventario::class, 'incidencia_inventario_id');
    }

    public function actuaciones()
    {
        return $this->hasMany(AlertaRrhhActuacion::class, 'alerta_id');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function asignacionAnterior()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_anterior_id');
    }

    public function refaccion()
    {
        return $this->belongsTo(VehiculoProductoAsignacion::class, 'vehiculo_producto_asignacion_id');
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? self::ESTADOS[self::ESTADO_PENDIENTE];
    }

    public function getMotivoEtiquetaAttribute(): string
    {
        return $this->incidencia?->motivo_etiqueta ?? ($this->vehiculo_vin ? 'Daño de refacción' : 'Incidencia histórica');
    }

    public function getBodegaNombreAttribute(): ?string
    {
        return $this->incidencia?->asignacion?->bodega?->nombre
            ?? $this->asignacionAnterior?->bodega?->nombre ?? $this->refaccion?->bodega?->nombre;
    }

    public function getFechaIncidenteAttribute()
    {
        return $this->incidencia ? $this->incidencia->ocurrio_en : $this->fecha_dano_reemplazo;
    }

    public function getVidaEtiquetaAttribute(): string
    {
        return match ($this->incidencia?->estado_vida) {
            'vigente' => 'Vigente ('.max(1, (int) ceil($this->incidencia->vida_restante_segundos / 86400)).' días)',
            'vencida' => 'Vencida',
            'desconocida' => 'Desconocida',
            default => $this->vida_util_meses > 0 ? $this->meses_restantes.' meses registrados' : 'Desconocida',
        };
    }

    public function colaborador()
    {
        return $this->belongsTo(Colaborador::class, 'colaborador_codigo', 'codigo');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_codigo', 'codigo');
    }
}
