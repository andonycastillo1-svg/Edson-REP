<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ContrastarSaldosInventario extends Command
{
    protected $signature = 'inventario:contrastar-saldos';

    protected $description = 'Compara saldos, existencias y asignaciones para preparar la conciliación';

    public function handle(): int
    {
        $saldos = DB::table('inventarios')
            ->select('producto_codigo', 'bodega_id', 'stock_tipo')
            ->selectRaw('SUM(cantidad) AS saldo, 0 AS unidades, COUNT(*) AS filas')
            ->groupBy('producto_codigo', 'bodega_id', 'stock_tipo');

        $existencias = DB::table('inventario_existencias')->whereNotNull('bodega_id')
            ->select('producto_codigo', 'bodega_id', 'condicion AS stock_tipo')
            ->selectRaw('0 AS saldo, COUNT(*) AS unidades, 0 AS filas')
            ->groupBy('producto_codigo', 'bodega_id', 'condicion');

        $diferencias = DB::query()->fromSub($saldos->unionAll($existencias), 's')
            ->select('producto_codigo', 'bodega_id', 'stock_tipo')
            ->selectRaw('SUM(saldo) AS saldo, SUM(unidades) AS unidades, SUM(filas) AS filas')
            ->groupBy('producto_codigo', 'bodega_id', 'stock_tipo')
            ->havingRaw('SUM(saldo) <> SUM(unidades) OR SUM(filas) > 1')
            ->orderBy('bodega_id')->orderBy('producto_codigo');

        $periodosActivos = DB::table('asignacion_periodos')->whereNull('devuelto_en')
            ->select('asignacion_inventario_id')->selectRaw('COUNT(*) AS unidades')
            ->groupBy('asignacion_inventario_id');
        $asignaciones = DB::table('asignaciones_inventarios AS a')->leftJoinSub($periodosActivos, 'p', 'p.asignacion_inventario_id', '=', 'a.id')
            ->whereRaw("CASE WHEN a.estado = 'Activa' THEN a.cantidad_asignada ELSE 0 END <> COALESCE(p.unidades, 0)");

        $unidadesVehiculo = DB::table('inventario_existencias')->where('condicion', 'asignado_vehiculo')
            ->whereNotNull('vehiculo_producto_asignacion_id')
            ->select('vehiculo_producto_asignacion_id')->selectRaw('COUNT(*) AS unidades')
            ->groupBy('vehiculo_producto_asignacion_id');
        $asignacionesVehiculo = DB::table('vehiculo_producto_asignaciones AS v')->leftJoinSub($unidadesVehiculo, 'u', 'u.vehiculo_producto_asignacion_id', '=', 'v.id')
            ->whereRaw('CASE WHEN v.activa THEN v.cantidad ELSE 0 END <> COALESCE(u.unidades, 0)');

        $total = 0;
        foreach ($diferencias->cursor() as $fila) {
            $this->line("Bodega {$fila->bodega_id} / {$fila->producto_codigo} / {$fila->stock_tipo} — saldo {$fila->saldo}, unidades {$fila->unidades}, filas {$fila->filas}");
            $total++;
        }
        foreach ($asignaciones->select('a.id')->orderBy('a.id')->cursor() as $fila) {
            $this->line("Asignación de colaborador #{$fila->id} — períodos activos por conciliar");
            $total++;
        }
        foreach ($asignacionesVehiculo->select('v.id')->orderBy('v.id')->cursor() as $fila) {
            $this->line("Asignación de refacción #{$fila->id} — unidades por conciliar");
            $total++;
        }

        $this->info("Diferencias encontradas: {$total}");

        return $total === 0 ? self::SUCCESS : self::FAILURE;
    }
}
