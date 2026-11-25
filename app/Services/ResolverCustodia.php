<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\AsignacionMovimiento;
use App\Models\IncidenciaInventario;
use App\Models\User;
use App\Sistema\Auditoria;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResolverCustodia
{
    public const CONDICIONES = [
        'buen_estado' => 'Devolución en buen estado',
        'danado' => 'Devolución dañada',
        'perdido' => 'Pérdida',
        'robado' => 'Robo',
        'otra' => 'Otra incidencia',
        'baja' => 'Retorno para baja',
    ];

    public const RESULTADOS_OTRA = [
        'buen_estado' => 'Retorno reutilizable',
        'danado' => 'Retorno dañado',
        'baja' => 'Retorno para baja',
        'sin_retorno' => 'Cierre sin retorno físico',
    ];

    public function __construct(
        private readonly AccesoCustodia $acceso,
        private readonly InventarioStockService $stock,
        private readonly AsignacionVidaUtilService $vida,
        private readonly Auditoria $auditoria,
    ) {}

    public static function reglas(string $prefijo = ''): array
    {
        return [
            $prefijo.'estado_devolucion' => ['nullable', Rule::in(array_keys(self::CONDICIONES))],
            $prefijo.'resultado_otra' => ['nullable', Rule::in(array_keys(self::RESULTADOS_OTRA))],
            $prefijo.'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
            $prefijo.'fecha_incidente' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function validar(AsignacionInventario $asignacion, int $cantidad, array $datos, User $usuario): void
    {
        abort_unless($this->acceso->puedeResolver($usuario, $asignacion), 403);

        if ($asignacion->estado !== 'Activa' || $cantidad < 1 || $cantidad > $asignacion->cantidad_asignada) {
            throw ValidationException::withMessages(['cantidad_devuelta' => 'La cantidad supera el saldo bajo custodia o la custodia ya está cerrada.']);
        }
        if (empty($datos['estado_devolucion'])) {
            throw ValidationException::withMessages(['estado_devolucion' => 'Selecciona qué ocurrió con el producto.']);
        }
        if (
            $datos['estado_devolucion'] === 'otra'
            && (blank($datos['detalle_devolucion'] ?? null) || empty($datos['resultado_otra']))
        ) {
            throw ValidationException::withMessages(['resultado_otra' => 'Indica qué ocurrió y si hubo un retorno físico.']);
        }

        if (!empty($datos['fecha_incidente']) && Carbon::parse($datos['fecha_incidente'])->lt($asignacion->entrega?->salida_en ?? $asignacion->fecha)) {
            throw ValidationException::withMessages(['fecha_incidente' => 'La fecha del hecho debe ser posterior o igual a la salida.']);
        }
    }

    public function aplicar(
        AsignacionInventario $asignacion,
        int $cantidad,
        array $datos,
        User $usuario,
        string $grupo,
        string $motivoAdministrativo = 'ordinaria',
        ?int $alertaReemplazoId = null,
        ?int $reemplazoId = null,
    ): array {
        $this->validar($asignacion, $cantidad, $datos, $usuario);

        $motivo = $datos['estado_devolucion'];
        $resultado = $motivo === 'otra' ? $datos['resultado_otra'] : $motivo;
        $retorno = !in_array($resultado, InventarioLifecycleService::SIN_RETORNO, true);
        $detalle = $datos['detalle_devolucion'] ?? self::CONDICIONES[$motivo];
        $ocurrio = empty($datos['fecha_incidente']) ? null : Carbon::parse($datos['fecha_incidente']);
        $segundos = $motivo === 'buen_estado' ? null : $this->vida->restante($asignacion, $ocurrio, $cantidad);

        $vidaRetorno = $resultado === 'buen_estado'
            ? $this->vida->restante($asignacion, $ocurrio ?? now(), $cantidad) : null;
        $fechaMovimiento = $ocurrio ?? now();
        $this->stock->devolver($asignacion, $cantidad, $resultado, $detalle, $fechaMovimiento, $vidaRetorno);

        $antes = $asignacion->cantidad_asignada;
        $asignacion->cantidad_asignada -= $cantidad;
        if ($asignacion->cantidad_asignada === 0) {
            $asignacion->estado = match ($resultado) {
                'danado' => 'Dañada',
                'buen_estado', 'baja' => 'Devuelta',
                default => 'Cerrada',
            };
        }
        $asignacion->save();

        $tipo = $resultado === 'buen_estado' ? 'usado' : $resultado;
        $devolucionCompleta = $motivoAdministrativo === AsignacionMovimiento::DEVOLUCION_COMPLETA;
        if ($retorno || $devolucionCompleta) {
            AsignacionMovimiento::create([
                'asignacion_inventario_id' => $asignacion->id,
                'tipo' => !$devolucionCompleta && ($reemplazoId ?? $asignacion->sustituida_por_id) ? 'Reemplazo' : 'Devolucion',
                'motivo_administrativo' => $motivoAdministrativo,
                'ocurrio_en' => $fechaMovimiento,
                'vida_restante_segundos' => $retorno && $resultado === 'buen_estado' ? $vidaRetorno : $segundos,
                'cantidad' => $cantidad,
                'detalle' => $detalle,
                'grupo_devolucion' => $grupo,
                'estado_devolucion' => $resultado,
                'stock_tipo_resultante' => $retorno ? $tipo : null,
                'bodega_retorno_id' => $retorno ? $asignacion->bodega_id : null,
                'user_id' => $usuario->id,
            ]);
        }

        $alerta = null;
        if ($motivo !== 'buen_estado') {
            $incidencia = $asignacion->incidencias()->create([
                'reemplazo_id' => $reemplazoId ?? $asignacion->sustituida_por_id,
                'registrado_por' => $usuario->id,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
                'resultado_stock' => $retorno ? $tipo : 'sin_retorno',
                'ocurrio_en' => $ocurrio,
                'observaciones' => $datos['detalle_devolucion'] ?? null,
                'estado_vida' => match (true) {
                    $segundos === null => 'desconocida',
                    $segundos > 0 => 'vigente',
                    default => 'vencida',
                },
                'vida_restante_segundos' => $segundos,
            ]);
            if ($alertaReemplazoId !== null) {
                AlertaReemplazo::whereKey($alertaReemplazoId)->update(['incidencia_inventario_id' => $incidencia->id]);
            } elseif ($devolucionCompleta && (in_array($motivo, ['perdido', 'robado'], true) || ($resultado === 'danado' && $segundos !== 0))) {
                $alerta = AlertaReemplazo::where('asignacion_anterior_id', $asignacion->id)
                    ->whereNotNull('motivo_revision')->whereNull('incidencia_inventario_id')
                    ->where('estado', '!=', AlertaReemplazo::ESTADO_FINALIZADO)->latest('id')->lockForUpdate()->first();
                if ($alerta) {
                    $alerta->update(['incidencia_inventario_id' => $incidencia->id]);
                } else {
                    $alerta = $incidencia->alerta()->create([
                        'colaborador_codigo' => $asignacion->colaborador_codigo,
                        'producto_codigo' => $asignacion->producto_codigo,
                        'asignacion_anterior_id' => $asignacion->id,
                        'asignacion_nueva_id' => $asignacion->sustituida_por_id,
                        'fecha_asignacion_anterior' => $asignacion->fecha,
                        'fecha_dano_reemplazo' => $ocurrio ?? $incidencia->created_at,
                        'vida_util_meses' => $asignacion->vida_util_original_meses ?? 0,
                        'meses_restantes' => $segundos === null ? 0 : (int) ceil($segundos / (30 * 86400)),
                        'descuento_aplicable' => false,
                        'estado' => AlertaReemplazo::ESTADO_PENDIENTE,
                        'registrado_por_user_id' => $usuario->id,
                        'cantidad' => $cantidad,
                        'detalle' => $detalle,
                    ]);
                }
            }
        }

        $estado = match ($asignacion->estado) {
            'Activa' => 'activo',
            'Dañada' => 'danado',
            default => 'cerrado',
        };
        $this->vida->registrarEstado($asignacion, $estado, $detalle);
        $this->auditoria->registrar('asignaciones', $retorno ? 'devolver' : 'registrar_incidencia', $asignacion->id,
            ['cantidad' => $antes], [
                'cantidad' => $asignacion->cantidad_asignada, 'motivo' => $motivo, 'resultado' => $tipo,
                'reemplazo_id' => $reemplazoId ?? $asignacion->sustituida_por_id, 'motivo_administrativo' => $motivoAdministrativo,
            ]);

        return ['retorno' => $retorno, 'alerta' => $alerta?->id];
    }
}
