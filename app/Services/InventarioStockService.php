<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\InventarioExistencia;
use App\Models\Producto;
use App\Models\VehiculoProductoAsignacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioStockService
{
    public function __construct(private InventarioLifecycleService $lifecycle) {}

    public function incrementar(int $bodegaId, string $codigo, int $cantidad, ?float $valor = null): void
    {
        $this->bloquearProducto($codigo);
        $this->saldo($bodegaId, $codigo, 'nuevo');

        if ($this->porUnidad()) {
            $this->lifecycle->crearNuevas($codigo, $bodegaId, $cantidad, $valor);
        }

        $this->ajustar($bodegaId, $codigo, 'nuevo', $cantidad);
    }

    public function trasladar(int $origen, int $destino, string $codigo, int $cantidad): void
    {
        $this->bloquearProducto($codigo);
        $this->exigirStock($origen, $codigo, 'nuevo', $cantidad);
        $this->saldo($destino, $codigo, 'nuevo');

        if ($this->porUnidad()) {
            $ids = $this->existencias($origen, $codigo, 'nuevo')->orderBy('id')->limit($cantidad)->lockForUpdate()->pluck('id');
            InventarioExistencia::whereKey($ids)->update(['bodega_id' => $destino]);
        }

        $this->ajustar($origen, $codigo, 'nuevo', -$cantidad);
        $this->ajustar($destino, $codigo, 'nuevo', $cantidad);
    }

    public function asignar(AsignacionInventario $asignacion, string $tipo): Collection
    {
        $bodega = (int) $asignacion->bodega_id;
        $codigo = $asignacion->producto_codigo;
        $cantidad = $asignacion->cantidad_asignada;
        $this->bloquearProducto($codigo);
        $this->exigirStock($bodega, $codigo, $tipo, $cantidad);

        $unidades = $this->porUnidad()
            ? $this->lifecycle->reservar($asignacion, $tipo, $cantidad, Carbon::parse($asignacion->fecha))
            : collect();

        $this->ajustar($bodega, $codigo, $tipo, -$cantidad);

        return $unidades;
    }

    public function devolver(AsignacionInventario $asignacion, int $cantidad, string $estado, string $detalle): Collection
    {
        $bodega = (int) $asignacion->bodega_id;
        $codigo = $asignacion->producto_codigo;
        $tipo = $estado === 'buen_estado' ? 'usado' : $estado;
        $this->bloquearProducto($codigo);
        if ($tipo !== 'perdido') {
            $this->saldo($bodega, $codigo, $tipo);
        }

        $periodos = collect();
        if ($this->porUnidad()) {
            $periodos = $this->lifecycle->devolver($asignacion, $cantidad, $estado, $bodega, $detalle);
        }

        if ($tipo !== 'perdido') {
            $this->ajustar($bodega, $codigo, $tipo, $cantidad);
        }

        return $periodos;
    }

    public function asignarAVehiculo(VehiculoProductoAsignacion $asignacion): void
    {
        $bodega = (int) $asignacion->bodega_id;
        $codigo = $asignacion->producto_codigo;
        $cantidad = (int) $asignacion->cantidad;
        $this->bloquearProducto($codigo);
        $nuevo = min($cantidad, $this->saldo($bodega, $codigo, 'nuevo'));
        $usado = $cantidad - $nuevo;
        if ($usado > 0) {
            $this->exigirStock($bodega, $codigo, 'usado', $usado);
        }

        foreach (['nuevo' => $nuevo, 'usado' => $usado] as $tipo => $unidades) {
            if ($unidades === 0) {
                continue;
            }

            if ($this->porUnidad()) {
                $ids = $this->existencias($bodega, $codigo, $tipo)->orderBy('id')->limit($unidades)->lockForUpdate()->pluck('id');
                InventarioExistencia::whereKey($ids)->update([
                    'condicion' => 'asignado_vehiculo',
                    'bodega_id' => null,
                    'disponible_desde' => null,
                    'vehiculo_producto_asignacion_id' => $asignacion->id,
                ]);
            }
            $this->ajustar($bodega, $codigo, $tipo, -$unidades);
        }
    }

    public function cerrarDeVehiculo(VehiculoProductoAsignacion $asignacion, int $cantidad, string $accion): void
    {
        $bodega = (int) $asignacion->bodega_id;
        $codigo = $asignacion->producto_codigo;
        $tipo = match ($accion) {
            'regresar' => 'usado',
            'danado' => 'danado',
            'baja' => 'baja',
            default => null,
        };
        $this->bloquearProducto($codigo);
        if ($tipo !== null) {
            $this->saldo($bodega, $codigo, $tipo);
        }

        if ($this->porUnidad()) {
            $unidades = InventarioExistencia::where('vehiculo_producto_asignacion_id', $asignacion->id)
                ->where('condicion', 'asignado_vehiculo')->orderBy('id')->lockForUpdate()->get();
            if ($unidades->count() !== (int) $asignacion->cantidad) {
                $this->requiereConciliacion($codigo, $bodega);
            }

            InventarioExistencia::whereKey($unidades->take($cantidad)->modelKeys())->update([
                'condicion' => $tipo ?? 'consumido',
                'bodega_id' => $tipo === null ? null : $bodega,
                'disponible_desde' => $tipo === 'usado' ? now() : null,
                'vida_util_restante_segundos' => null,
            ]);
        }

        if ($tipo !== null) {
            $this->ajustar($bodega, $codigo, $tipo, $cantidad);
        }
    }

    private function porUnidad(): bool
    {
        return (bool) config('inventario.seguimiento_unidades');
    }

    private function bloquearProducto(string $codigo): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Las operaciones de inventario requieren una transacción.');
        }

        Producto::whereKey($codigo)->lockForUpdate()->firstOrFail();
    }

    private function existencias(int $bodega, string $codigo, string $tipo)
    {
        return InventarioExistencia::where('bodega_id', $bodega)
            ->where('producto_codigo', $codigo)->where('condicion', $tipo);
    }

    private function inventario(int $bodega, string $codigo, string $tipo)
    {
        return DB::table('inventarios')->where([
            'bodega_id' => $bodega, 'producto_codigo' => $codigo, 'stock_tipo' => $tipo,
        ]);
    }

    private function saldo(int $bodega, string $codigo, string $tipo): int
    {
        $filas = $this->inventario($bodega, $codigo, $tipo)->lockForUpdate()->get();
        $saldo = (int) ($filas->first()->cantidad ?? 0);
        if ($filas->count() > 1 || $saldo < 0 ||
            ($this->porUnidad() && $this->existencias($bodega, $codigo, $tipo)->lockForUpdate()->pluck('id')->count() !== $saldo)) {
            $this->requiereConciliacion($codigo, $bodega);
        }

        return $saldo;
    }

    private function exigirStock(int $bodega, string $codigo, string $tipo, int $cantidad): void
    {
        if ($this->saldo($bodega, $codigo, $tipo) < $cantidad) {
            throw ValidationException::withMessages(['stock' => "Stock {$tipo} insuficiente para {$codigo}."]);
        }
    }

    private function ajustar(int $bodega, string $codigo, string $tipo, int $delta): void
    {
        $consulta = $this->inventario($bodega, $codigo, $tipo);
        $fila = $consulta->lockForUpdate()->first();
        $datos = [
            'cantidad' => (int) ($fila->cantidad ?? 0) + $delta,
            'updated_at' => now(),
        ];
        if ($this->porUnidad() && $tipo === 'usado') {
            $vidaMinima = $this->existencias($bodega, $codigo, $tipo)
                ->whereNotNull('vida_util_restante_segundos')->orderBy('vida_util_restante_segundos')
                ->lockForUpdate()->value('vida_util_restante_segundos');
            $datos['vida_util_restante_meses'] = $vidaMinima === null ? null : intdiv((int) $vidaMinima, 30 * 86400);
        }

        if ($fila) {
            $consulta->update($datos);
        } else {
            $id = DB::table('inventarios')->insertGetId($datos + [
                'bodega_id' => $bodega, 'producto_codigo' => $codigo, 'stock_tipo' => $tipo, 'created_at' => now(),
            ]);
            $campos = app(\App\Sistema\ValoresCampos::class);
            $campos->guardar('inventario', $id, $campos->predeterminadosAutomaticos('inventario'));
        }
        app(\App\Sistema\Auditoria::class)->registrar('inventario', 'movimiento', $codigo.'@'.$bodega.'@'.$tipo, [
            'cantidad' => (int) ($fila->cantidad ?? 0),
        ], ['cantidad' => $datos['cantidad']]);

        $minimo = app(\App\Sistema\Ajustes::class)->general('stock_minimo');
        if ($minimo && in_array($tipo, ['nuevo', 'usado'], true)) {
            $disponible = DB::table('inventarios')->where('producto_codigo', $codigo)->where('bodega_id', $bodega)
                ->whereIn('stock_tipo', ['nuevo', 'usado'])->lockForUpdate()->pluck('cantidad')->sum();
            if ($disponible < $minimo && $disponible - $delta >= $minimo) {
                $id = $consulta->value('id');
                $actor = auth()->user();
                DB::afterCommit(function () use ($id, $disponible, $actor) {
                    $inventario = \App\Models\Inventario::find($id);
                    if ($inventario) {
                        $inventario->cantidad = $disponible;
                        app(NotificacionService::class)->safeAction(
                            fn ($notificaciones) => $notificaciones->notificarStockBajo($inventario, $actor)
                        );
                    }
                });
            }
        }
    }

    private function requiereConciliacion(string $codigo, int $bodega): never
    {
        throw ValidationException::withMessages([
            'stock' => "Inventario de {$codigo} en bodega {$bodega} requiere conciliación.",
        ]);
    }
}
