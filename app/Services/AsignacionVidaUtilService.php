<?php

namespace App\Services;

use App\Models\AsignacionEstadoHistorial;
use App\Models\AsignacionInventario;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class AsignacionVidaUtilService
{
    public function registrarInicio(AsignacionInventario $asignacion, CarbonInterface $inicio): void
    {
        if ($asignacion->estado !== 'Activa' || $asignacion->cantidad_asignada < 1) {
            return;
        }

        $periodos = $asignacion->periodos()->whereNull('devuelto_en');
        $vidas = (clone $periodos)->pluck('vida_util_al_asignar_segundos');

        if ($vidas->isNotEmpty()) {
            $periodos->update(['inicio_uso_en' => $inicio]);
            $segundos = $vidas->containsStrict(null) ? null : (int) $vidas->min();
            $vencimiento = $segundos === null ? null : $inicio->copy()->addSeconds($segundos);
        } elseif ($asignacion->vida_util_disponible_segundos !== null) {
            $vencimiento = $inicio->copy()->addSeconds($asignacion->vida_util_disponible_segundos);
        } else {
            $meses = $asignacion->vida_util_restante_meses;
            $vencimiento = $meses === null ? null : $inicio->copy()->addMonthsNoOverflow($meses);
        }

        $asignacion->update(['fecha_vencimiento' => $vencimiento]);
    }

    public function restante(AsignacionInventario $asignacion, ?CarbonInterface $fechaCorte, ?int $cantidad = null): ?int
    {
        if ($fechaCorte === null) {
            return null;
        }

        if (config('inventario.seguimiento_unidades')) {
            $cantidad ??= $asignacion->cantidad_asignada;
            $periodos = $asignacion->relationLoaded('periodosActivos')
                ? $asignacion->periodosActivos->take($cantidad)
                : $asignacion->periodosActivos()->limit($cantidad)->get();
            if ($periodos->isNotEmpty()) {
                $vidas = $periodos->map(function ($periodo) use ($fechaCorte, $asignacion) {
                    $inicio = $periodo->inicio_uso_en ?? ($asignacion->entrega === null ? $periodo->asignado_en : null);
                    $vida = $periodo->vida_util_al_asignar_segundos;
                    if ($vida === null || $inicio === null || $fechaCorte->lt($inicio)) {
                        return null;
                    }

                    return max(0, (int) $vida - max(0, (int) $inicio->diffInSeconds($fechaCorte)));
                });
                if ($vidas->filter(fn ($vida) => $vida > 0)->isNotEmpty()) {
                    return (int) $vidas->filter(fn ($vida) => $vida > 0)->min();
                }

                return $vidas->containsStrict(null) ? null : 0;
            }
        }

        $vencimiento = $asignacion->fecha_vencimiento;
        $entrega = $asignacion->entrega;
        if ($entrega?->recibida_en && $fechaCorte->lt($entrega->recibida_en)) {
            return null;
        }
        if ($vencimiento === null && $entrega === null) {
            $meses = $asignacion->vida_util_restante_meses
                ?? ($asignacion->stock_tipo === 'usado' ? null : $asignacion->vida_util_original_meses);
            $vencimiento = $meses === null ? null : $asignacion->fecha->copy()->addMonthsNoOverflow($meses);
        }

        return $vencimiento === null ? null : max(0, (int) $fechaCorte->diffInSeconds($vencimiento));
    }

    public function registrarEstado(AsignacionInventario $asignacion, string $estado, ?string $detalle = null): void
    {
        if (!Schema::hasTable('asignacion_estado_historiales')) {
            return;
        }

        AsignacionEstadoHistorial::create([
            'asignacion_inventario_id' => $asignacion->id,
            'estado' => $estado,
            'fecha_evento' => now(),
            'detalle' => $detalle,
            'user_id' => auth()->id(),
        ]);
    }
}
