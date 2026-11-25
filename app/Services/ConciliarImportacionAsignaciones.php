<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\ImportacionAsignacion;
use App\Models\User;
use App\Sistema\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConciliarImportacionAsignaciones
{
    public function __construct(private RequerimientosAsignacion $requerimientos, private Auditoria $auditoria, private AccesoInventarioOperativo $acceso) {}

    public function conciliar(User $actor, Bodega $bodega, string $id, int $destino, string $evidencia): int
    {
        return DB::transaction(function () use ($actor, $bodega, $id, $destino, $evidencia) {
            $lote = ImportacionAsignacion::where('user_id', $actor->id)->where('bodega_id', $bodega->id)
                ->whereNotNull('confirmada_en')->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($lote->consolidado_id && (int) $lote->consolidado_id !== $destino) {
                $this->error('El lote ya identifica otro consolidado.');
            }
            $codigos = collect($lote->filas)->pluck('colaborador_codigo')->unique();
            $regiones = Colaborador::whereKey($codigos->all())->orderBy('codigo')->lockForUpdate()->pluck('region_id');
            abort_unless($regiones->count() === $codigos->count(), 403);
            $alcance = $this->acceso->autorizar($actor, 'flujo.importar', [(int) $bodega->id], $regiones->all(), bloquear: true);
            $consolidado = $this->requerimientos->verificar($actor, $destino, bloquear: true, permiso: 'flujo.importar');
            $plan = collect($lote->filas)->groupBy('colaborador_codigo');
            $manifiesto = collect($lote->entregas ?? []);
            $grupos = $manifiesto->pluck('grupo');
            if ($plan->isEmpty() || $manifiesto->count() !== $plan->count() || $grupos->duplicates()->isNotEmpty()
                || $grupos->contains(fn ($grupo) => !Str::isUuid($grupo)) || $manifiesto->pluck('codigo')->duplicates()->isNotEmpty()) {
                $this->error('No se puede comprobar la operación original de todas las filas. Revisa las hojas y el historial.');
            }

            $registradas = AsignacionInventario::with('entrega', 'movimientoInicial', 'requerimiento.participante')
                ->whereIn('grupo_asignacion', $grupos)->orderBy('id')->lockForUpdate()->get()->groupBy('grupo_asignacion');
            $cambios = [];
            foreach ($manifiesto as $entrega) {
                $codigo = (string) $entrega['codigo'];
                $filas = $plan->get($codigo, collect())->keyBy('producto_codigo');
                $asignaciones = $registradas->get($entrega['grupo'], collect());
                if ($filas->isEmpty() || $asignaciones->count() !== $filas->sum('cantidad') || $filas->count() !== (int) $entrega['productos']) {
                    $this->error('Las asignaciones registradas no coinciden con la cantidad de productos del lote.');
                }
                $porProducto = $asignaciones->groupBy('producto_codigo');
                if ($porProducto->count() !== $filas->count()) {
                    $this->error('El conjunto de productos del lote requiere revisión.');
                }
                foreach ($asignaciones as $asignacion) {
                    $fila = $filas->get($asignacion->producto_codigo);
                    $movimiento = $asignacion->movimientoInicial;
                    $necesidad = $asignacion->requerimiento;
                    if (!$fila || (string) $asignacion->colaborador_codigo !== $codigo || $asignacion->fecha?->toDateString() !== $fila['fecha']
                        || $asignacion->entrega?->salida_en?->toDateString() !== $fila['fecha']
                        || (int) $asignacion->bodega_id !== (int) $lote->bodega_id || (int) $asignacion->user_id !== (int) $lote->user_id
                        || (int) $movimiento?->cantidad !== 1 || $movimiento?->ocurrio_en?->toDateString() !== $fila['fecha']
                        || (int) $movimiento?->user_id !== (int) $lote->user_id
                        || $porProducto[$asignacion->producto_codigo]->count() !== (int) $fila['cantidad']) {
                        $this->error('Una asignación no coincide con el colaborador, el producto, la fecha o el movimiento original. Revisa el caso.');
                    }
                    if ($asignacion->requerimiento_id && (!$necesidad || (int) $necesidad->participante->consolidado_id !== $destino
                        || $necesidad->producto_codigo !== $asignacion->producto_codigo || $necesidad->participante->colaborador_codigo !== $codigo)) {
                        $this->error('Una asignación ya pertenece a otro requerimiento.');
                    }
                }
                $sinOrigen = $asignaciones->whereNull('requerimiento_id');
                if ($sinOrigen->isEmpty()) {
                    continue;
                }
                $articulos = $sinOrigen->map(fn ($fila) => ['producto_codigo' => $fila->producto_codigo, 'cantidad' => 1]);
                $porCodigo = $this->requerimientos->vincular($actor, $destino, $codigo, $articulos, permiso: 'flujo.importar');
                foreach ($sinOrigen as $asignacion) {
                    $asignacion->requerimiento_id = $porCodigo->get($asignacion->producto_codigo);
                    $asignacion->save();
                    $cambios[] = ['asignacion' => $asignacion->id, 'requerimiento' => $asignacion->requerimiento_id,
                        'grupo' => $asignacion->grupo_asignacion, 'fecha' => $asignacion->fecha->toDateString()];
                }
            }
            if ($cambios !== []) {
                $origenAnterior = $lote->consolidado_id;
                $lote->update(['consolidado_id' => $consolidado->id]);
                $this->acceso->registrar($alcance, 'flujo.importar', $lote->id);
                $this->auditoria->registrar('asignaciones', 'conciliar_importacion', $lote->id, ['consolidado_id' => $origenAnterior],
                    ['consolidado_id' => $consolidado->id, 'evidencia' => $evidencia, 'vinculos' => $cambios]);
            }

            return count($cambios);
        }, 3);
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['conciliacion' => $mensaje]);
    }
}
