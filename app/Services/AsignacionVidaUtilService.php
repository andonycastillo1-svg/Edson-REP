<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionEstadoHistorial;
use App\Models\AsignacionInventario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AsignacionVidaUtilService
{
    public function mesesRestantes(AsignacionInventario $asignacion, ?Carbon $fechaCorte = null): int
    {
        $fechaCorte ??= now();
        $vencimiento = $asignacion->fecha_vencimiento;
        if ($vencimiento === null) {
            $meses = $asignacion->vida_util_restante_meses
                ?? ($asignacion->stock_tipo === 'usado' ? null : $asignacion->vida_util_original_meses);
            if ($meses === null) {
                return 0;
            }

            $vencimiento = Carbon::parse($asignacion->fecha)->addMonthsNoOverflow($meses);
        }

        return (int) ceil(max(0, $fechaCorte->diffInMonths($vencimiento, false)));
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

    public function procesarReemplazoPorDanio(
        AsignacionInventario $asignacionAnterior,
        AsignacionInventario $asignacionNueva,
        Carbon $fechaDanio,
        bool $cerrarAnterior = true
    ): ?AlertaReemplazo {
        $alertaRrhh = null;
        $vidaMeses = (int) ($asignacionAnterior->vida_util_original_meses ?? $asignacionAnterior->producto->vida_util_meses ?? 0);
        $mesesRestantes = $this->mesesRestantes($asignacionAnterior, $fechaDanio);

        if ($mesesRestantes > 0) {
            if ($cerrarAnterior) {
                $asignacionAnterior->estado = 'Dañada';
                $this->registrarEstado($asignacionAnterior, 'danado', 'Producto dañado antes de finalizar vida útil.');
            }

            if (Schema::hasTable('alertas_reemplazos_rrhh')) {
                $alertaRrhh = AlertaReemplazo::create([
                    'colaborador_codigo' => $asignacionAnterior->colaborador_codigo,
                    'producto_codigo' => $asignacionAnterior->producto_codigo,
                    'registrado_por_user_id' => auth()->id(),
                    'asignacion_anterior_id' => $asignacionAnterior->id,
                    'asignacion_nueva_id' => $asignacionNueva->id,
                    'fecha_asignacion_anterior' => $asignacionAnterior->fecha,
                    'fecha_dano_reemplazo' => $fechaDanio,
                    'vida_util_meses' => $vidaMeses,
                    'meses_restantes' => $mesesRestantes,
                    'descuento_aplicable' => true,
                    'estado' => 'pendiente',
                    'detalle' => 'Reemplazo antes de vida útil; aplica revisión de descuento proporcional RRHH.',
                ]);
            }
        } elseif ($cerrarAnterior) {
            $asignacionAnterior->estado = 'Devuelta';
            $this->registrarEstado($asignacionAnterior, 'depreciado', 'Producto fuera de vida útil (depreciado).');
            $this->registrarEstado($asignacionNueva, 'reutilizado', 'Reasignado fuera de vida útil (sin descuento).');
        }

        if (!$cerrarAnterior) {
            $this->registrarEstado($asignacionAnterior, 'activo', 'Se reemplazó parte de la asignación por daño.');
        }

        return $alertaRrhh;
    }
}
