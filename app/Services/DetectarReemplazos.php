<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\MotivoAsignacion;
use App\Sistema\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DetectarReemplazos
{
    public function __construct(private AsignacionVidaUtilService $vida, private Auditoria $auditoria) {}

    public function registrar(AsignacionInventario $nueva, Collection $anteriores, ?MotivoAsignacion $motivo = null): array
    {
        $salida = $nueva->entrega->salida_en;
        $alertas = [];
        foreach ($anteriores as $anterior) {
            if ($salida->lt($anterior->entrega?->salida_en ?? $anterior->fecha)) {
                throw ValidationException::withMessages(['fecha' => 'La salida debe ser posterior o igual a la entrega anterior.']);
            }
            $anterior->update(['sustituida_por_id' => $nueva->id]);
            $this->auditoria->registrar('asignaciones', 'vincular_custodia_anterior', $anterior->id, null,
                ['asignacion_nueva_id' => $nueva->id]);
            if ($motivo === null) {
                $restante = $this->vida->restante($anterior, $salida);
                if ($restante !== 0) {
                    $alertas[] = $this->alerta($nueva, $anterior, $restante)->id;
                }
            }
        }

        if ($motivo && $motivo->politica !== 'sin_propuesta') {
            $anterior = $anteriores->last();
            $restante = $anterior ? $this->vida->restante($anterior, $salida) : null;
            $alertas[] = $this->alerta($nueva, $anterior, $restante, $motivo)->id;
        }

        return $alertas;
    }

    private function alerta(AsignacionInventario $nueva, ?AsignacionInventario $anterior, ?int $restante, ?MotivoAsignacion $motivo = null): AlertaReemplazo
    {
        $meses = $anterior?->vida_util_original_meses ?? 0;
        $referencia = $motivo?->politica === 'proporcional' && $restante !== null && $meses > 0 && $anterior?->costo_unitario !== null
            ? round((float) $anterior->costo_unitario * min(1, $restante / ($meses * 30 * 86400)), 2) : null;

        return AlertaReemplazo::create([
            'colaborador_codigo' => $nueva->colaborador_codigo,
            'producto_codigo' => $nueva->producto_codigo,
            'asignacion_anterior_id' => $anterior?->id,
            'asignacion_nueva_id' => $nueva->id,
            'fecha_asignacion_anterior' => $anterior?->entrega?->salida_en ?? $anterior?->fecha,
            'fecha_dano_reemplazo' => $nueva->entrega->salida_en,
            'vida_util_meses' => $meses,
            'meses_restantes' => $restante === null ? 0 : (int) ceil($restante / (30 * 86400)),
            'vida_restante_segundos' => $restante,
            'motivo_revision' => $motivo ? 'motivo_asignacion'
                : ($restante === null ? AlertaReemplazo::REEMPLAZO_SIN_DATOS : AlertaReemplazo::REEMPLAZO_ANTICIPADO),
            'referencia_proporcional' => $referencia,
            'descuento_aplicable' => false,
            'estado' => AlertaReemplazo::ESTADO_PENDIENTE,
            'registrado_por_user_id' => $nueva->user_id,
            'cantidad' => $anterior?->cantidad_asignada ?? 1,
            'detalle' => $motivo ? 'Motivo declarado: '.$motivo->nombre.'. Su revisión es independiente de la salida.'
                : 'Nueva salida del mismo código con custodia anterior sin retorno confirmado.',
        ]);
    }
}
