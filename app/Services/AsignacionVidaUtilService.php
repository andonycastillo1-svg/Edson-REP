<?php

namespace App\Services;

use App\Models\AlertaReemplazo;
use App\Models\AsignacionEstadoHistorial;
use App\Models\AsignacionInventario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AsignacionVidaUtilService
{
    public function calcularMesesUsados(AsignacionInventario $asignacion, ?Carbon $fechaCorte = null): int
    {
        $fechaCorte = $fechaCorte ?: now();
        $fechaAsignacion = Carbon::parse($asignacion->fecha);
        return max(0, $fechaAsignacion->diffInMonths($fechaCorte, false));
    }

    public function mesesRestantes(AsignacionInventario $asignacion, ?Carbon $fechaCorte = null): int
    {
        $fechaCorte = $fechaCorte ?: now();
        $vidaMeses = (int) ($asignacion->producto->vida_util_meses ?? 0);

        if ($vidaMeses <= 0) {
            return 0;
        }

        $fechaAsignacion = Carbon::parse($asignacion->fecha);
        $mesesTranscurridos = $this->calcularMesesUsados($asignacion, $fechaCorte);

        return $vidaMeses - max(0, $mesesTranscurridos);
    }

    public function fechaVencimientoParaNuevaAsignacion(string $productoCodigo, Carbon $fechaAsignacion, int $vidaMesesProducto): ?Carbon
    {
        if ($vidaMesesProducto <= 0) {
            return null;
        }

        $ultimaAsignacion = AsignacionInventario::query()
            ->where('producto_codigo', $productoCodigo)
            ->whereNotNull('fecha_vencimiento')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if ($ultimaAsignacion?->fecha_vencimiento) {
            $vencimientoPrevio = Carbon::parse($ultimaAsignacion->fecha_vencimiento);
            return $vencimientoPrevio->greaterThan($fechaAsignacion)
                ? $vencimientoPrevio
                : $fechaAsignacion->copy();
        }

        return $fechaAsignacion->copy()->addMonths($vidaMesesProducto);
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
        Carbon $fechaDanio
    ): void {
        $vidaMeses = (int) ($asignacionAnterior->producto->vida_util_meses ?? 0);
        $mesesRestantes = $this->mesesRestantes($asignacionAnterior, $fechaDanio);
        $mesesUsados = $this->calcularMesesUsados($asignacionAnterior, $fechaDanio);
        $costo = (float) ($asignacionAnterior->costo_unitario ?? 0);
        $descuentoSugerido = $vidaMeses > 0 && $mesesRestantes > 0
            ? round(($mesesRestantes / $vidaMeses) * $costo, 2)
            : 0;

        if ($mesesRestantes > 0) {
            $asignacionAnterior->estado = 'Dañada';
            $this->registrarEstado($asignacionAnterior, 'danado', 'Producto dañado antes de finalizar vida útil.');

            if (Schema::hasTable('alertas_reemplazos_rrhh')) {
                AlertaReemplazo::create([
                    'colaborador_codigo' => $asignacionAnterior->colaborador_codigo,
                    'producto_codigo' => $asignacionAnterior->producto_codigo,
                    'producto_nombre' => $asignacionAnterior->producto->nombre ?? null,
                    'asignacion_anterior_id' => $asignacionAnterior->id,
                    'asignacion_nueva_id' => $asignacionNueva->id,
                    'fecha_asignacion_anterior' => $asignacionAnterior->fecha,
                    'fecha_dano_reemplazo' => $fechaDanio,
                    'vida_util_meses' => $vidaMeses,
                    'meses_restantes' => $mesesRestantes,
                    'meses_usados' => $mesesUsados,
                    'costo_producto' => $costo,
                    'descuento_proporcional_sugerido' => $descuentoSugerido,
                    'motivo_alerta' => 'reemplazo_danio',
                    'descuento_aplicable' => true,
                    'estado' => 'pendiente',
                    'detalle' => 'Reemplazo antes de vida útil; aplica revisión de descuento proporcional RRHH.',
                ]);
            }
        } else {
            $asignacionAnterior->estado = 'Devuelta';
            $this->registrarEstado($asignacionAnterior, 'depreciado', 'Producto fuera de vida útil (depreciado).');
            $this->registrarEstado($asignacionNueva, 'reutilizado', 'Reasignado fuera de vida útil (sin descuento).');
        }

        $asignacionAnterior->save();
    }
}
