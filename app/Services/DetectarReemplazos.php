<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Sistema\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DetectarReemplazos
{
    public function __construct(private AsignacionVidaUtilService $vida, private Auditoria $auditoria) {}

    public function registrar(AsignacionInventario $nueva, Collection $anteriores): array
    {
        $alertas = [];
        $salida = $nueva->entrega->salida_en;
        foreach ($anteriores as $anterior) {
            if ($salida->lt($anterior->entrega?->salida_en ?? $anterior->fecha)) {
                throw ValidationException::withMessages(['fecha' => 'La salida debe ser posterior o igual a la entrega anterior.']);
            }

            $anterior->update(['sustituida_por_id' => $nueva->id]);
            $this->auditoria->registrar('asignaciones', 'vincular_custodia_anterior', $anterior->id, null,
                ['asignacion_nueva_id' => $nueva->id]);
            $restante = $this->vida->restante($anterior, $salida);
            if ($restante === 0) {
                continue;
            }

            $alertas[] = AlertaReemplazo::create([
                'colaborador_codigo' => $anterior->colaborador_codigo,
                'producto_codigo' => $anterior->producto_codigo,
                'asignacion_anterior_id' => $anterior->id,
                'asignacion_nueva_id' => $nueva->id,
                'fecha_asignacion_anterior' => $anterior->entrega?->salida_en ?? $anterior->fecha,
                'fecha_dano_reemplazo' => $salida,
                'vida_util_meses' => $anterior->vida_util_original_meses ?? 0,
                'meses_restantes' => $restante === null ? 0 : (int) ceil($restante / (30 * 86400)),
                'vida_restante_segundos' => $restante,
                'motivo_revision' => $restante === null ? AlertaReemplazo::REEMPLAZO_SIN_DATOS : AlertaReemplazo::REEMPLAZO_ANTICIPADO,
                'descuento_aplicable' => false,
                'estado' => AlertaReemplazo::ESTADO_PENDIENTE,
                'registrado_por_user_id' => $nueva->user_id,
                'cantidad' => $anterior->cantidad_asignada,
                'detalle' => 'Nueva salida del mismo código con custodia anterior sin retorno confirmado.',
            ])->id;
        }

        return $alertas;
    }
}
