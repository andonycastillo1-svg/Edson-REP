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
        $fechaCorte = $fechaCorte ?: now();
        $vidaMeses = (int) ($asignacion->producto->vida_util_meses ?? 0);

        if ($vidaMeses <= 0) {
            return 0;
        }

        $fechaAsignacion = Carbon::parse($asignacion->fecha);
        $mesesTranscurridos = $fechaAsignacion->diffInMonths($fechaCorte, false);

        return $vidaMeses - max(0, $mesesTranscurridos);
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
    ): ?AlertaReemplazo {
        $alertaRrhh = null;
        $vidaMeses = (int) ($asignacionAnterior->producto->vida_util_meses ?? 0);
        $mesesRestantes = $this->mesesRestantes($asignacionAnterior, $fechaDanio);

        if ($mesesRestantes > 0) {
            $asignacionAnterior->estado = 'Dañada';
            $this->registrarEstado($asignacionAnterior, 'danado', 'Producto dañado antes de finalizar vida útil.');

            if (Schema::hasTable('alertas_reemplazos_rrhh')) {
                $alertaRrhh = AlertaReemplazo::create([
                    'colaborador_codigo' => $asignacionAnterior->colaborador_codigo,
                    'producto_codigo' => $asignacionAnterior->producto_codigo,
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
        } else {
            $asignacionAnterior->estado = 'Devuelta';
            $this->registrarEstado($asignacionAnterior, 'depreciado', 'Producto fuera de vida útil (depreciado).');
            $this->registrarEstado($asignacionNueva, 'reutilizado', 'Reasignado fuera de vida útil (sin descuento).');
        }

        $asignacionAnterior->save();

        return $alertaRrhh;
    }
}
