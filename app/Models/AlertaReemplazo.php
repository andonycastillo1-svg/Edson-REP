<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaReemplazo extends Model
{
    public const RELACIONES_LISTADO = ['colaborador', 'producto', 'registrador', 'incidencia.asignacion.bodega',
        'asignacionAnterior.bodega', 'asignacionNueva.entrega', 'refaccion.bodega'];

    public const REEMPLAZO_ANTICIPADO = 'reemplazo_anticipado';
    public const REEMPLAZO_SIN_DATOS = 'reemplazo_sin_datos';

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
        'motivo_revision',
        'politica_motivo',
        'referencia_proporcional',
        'vida_restante_segundos',
        'descuento_aplicable',
        'estado',
        'detalle',
    ];

    protected $casts = [
        'fecha_asignacion_anterior' => 'datetime',
        'fecha_dano_reemplazo' => 'datetime',
        'descuento_aplicable' => 'boolean',
        'vida_restante_segundos' => 'integer',
        'referencia_proporcional' => 'decimal:2',
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

    public function asignacionNueva()
    {
        return $this->belongsTo(AsignacionInventario::class, 'asignacion_nueva_id');
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
        return $this->incidencia?->motivo_etiqueta ?? match ($this->motivo_revision) {
            self::REEMPLAZO_ANTICIPADO => 'Posible reemplazo anticipado',
            self::REEMPLAZO_SIN_DATOS => 'Reemplazo por verificar',
            'motivo_asignacion' => $this->asignacionNueva?->motivo_registrado ?? 'Motivo de asignación',
            default => $this->vehiculo_vin ? 'Daño de refacción' : 'Incidencia histórica',
        };
    }

    public function getBodegaNombreAttribute(): ?string
    {
        return $this->incidencia?->asignacion?->bodega?->nombre
            ?? $this->asignacionAnterior?->bodega?->nombre ?? $this->asignacionNueva?->bodega?->nombre ?? $this->refaccion?->bodega?->nombre;
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
            default => match ($this->motivo_revision) {
                'motivo_asignacion' => match (true) {
                    $this->vida_restante_segundos === null => 'Desconocida',
                    $this->vida_restante_segundos === 0 => 'Vencida',
                    default => 'Vigente ('.(int) ceil($this->vida_restante_segundos / 86400).' días)',
                },
                self::REEMPLAZO_SIN_DATOS => 'Desconocida',
                self::REEMPLAZO_ANTICIPADO => 'Vigente ('.max(1, (int) ceil($this->vida_restante_segundos / 86400)).' días)',
                default => $this->vida_util_meses > 0 ? $this->meses_restantes.' meses registrados' : 'Desconocida',
            },
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
