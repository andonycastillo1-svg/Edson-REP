<?php

namespace App\Services;

use App\Models\AsignacionMovimiento;
use App\Models\Consolidado;
use App\Models\Inventario;
use App\Models\User;
use App\Sistema\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExportacionesOperativas
{
    public function __construct(private ExportarExcel $excel, private Permisos $permisos) {}

    public function autorizar(Request $request, string $modulo): void
    {
        abort_unless($this->permisos->permite($request->user(), $modulo.'.datos_exportar'), 403);
    }

    public function datos(User $usuario, string $modulo, array $titulos, iterable $registros)
    {
        if ($modulo !== 'productos' || !$this->permisos->permite($usuario, 'inventario.datos_exportar')) {
            $filas = (function () use ($registros) {
                foreach ($registros as [, $valores]) {
                    yield $valores;
                }
            })();

            return $this->excel->descargar($modulo, $titulos, $filas);
        }

        $bodegas = app(BodegaAccessService::class)->visibleBodegaIds($usuario);
        $filas = (function () use ($registros, $bodegas) {
            foreach ($registros as [$producto, $valores]) {
                $existencias = Inventario::with('bodega')->where('producto_codigo', $producto->codigo)
                    ->when($bodegas !== null, fn ($q) => $q->whereIn('bodega_id', $bodegas))->orderBy('id')->lazy(250);
                $tieneExistencias = false;
                foreach ($existencias as $stock) {
                    $tieneExistencias = true;
                    $disponibles = in_array($stock->stock_tipo, ['nuevo', 'usado'], true) ? (int) $stock->cantidad : 0;
                    yield [...$valores, $stock->bodega?->nombre, $stock->stock_tipo, (int) $stock->cantidad,
                        $disponibles, $disponibles > 0 ? 'Disponible' : 'Sin unidades disponibles'];
                }
                if (!$tieneExistencias) {
                    yield [...$valores, null, null, 0, 0, 'Sin existencias en bodegas visibles'];
                }
            }
        })();

        return $this->excel->descargar($modulo, [...$titulos, 'Bodega', 'Condición', 'Existencias', 'Disponibles', 'Estado de existencias'], $filas);
    }

    public function usuarios($consulta)
    {
        $filas = $consulta->with('regionesCoordinadas', 'coordinador')->orderBy('id')->lazy(200)->map(fn ($usuario) => [
            $usuario->name, $usuario->email, $usuario->role?->nombre, $usuario->acceso_activo ? 'Activo' : 'Inactivo',
            $usuario->regionesCoordinadas->pluck('nombre')->sort()->implode(', '), $usuario->bodega?->nombre,
            $usuario->coordinador?->name, $usuario->supervisoresAsignados->pluck('name')->sort()->implode(', '),
            $usuario->almacenistasAsignados->pluck('name')->sort()->implode(', '),
        ]);

        return $this->excel->descargar('usuarios', [
            'Nombre', 'Correo', 'Rol', 'Estado', 'Regiones asignadas', 'Bodega', 'Coordinador',
            'Supervisores vinculados', 'Almacenistas vinculados',
        ], $filas);
    }

    public function colaboradores($consulta)
    {
        return $this->excel->descargar('colaboradores', ['Código', 'Nombre', 'Puesto', 'Región', 'Estado'],
            $consulta->with('region')->orderBy('codigo')->lazy(250)->map(fn ($fila) => [
                $fila->codigo, $fila->nombre, $fila->puesto, $fila->region?->nombre, $fila->estado,
            ]));
    }

    public function existencias($bodegas)
    {
        $filas = (function () use ($bodegas) {
            foreach ($bodegas->reorder('id')->lazy(50) as $bodega) {
                $inventario = Inventario::with('producto')->where('bodega_id', $bodega->id)->orderBy('id')->lazy(250);
                $tieneFilas = false;
                foreach ($inventario as $stock) {
                    $tieneFilas = true;
                    yield [$bodega->nombre, $bodega->tipo, $stock->producto_codigo, $stock->producto?->nombre,
                        $stock->producto?->categoria, $stock->stock_tipo, (int) $stock->cantidad,
                        in_array($stock->stock_tipo, ['nuevo', 'usado'], true) ? (int) $stock->cantidad : 0, $stock->updated_at];
                }
                if (!$tieneFilas) {
                    yield [$bodega->nombre, $bodega->tipo];
                }
            }
        })();

        return $this->excel->descargar('existencias-por-bodega', [
            'Bodega', 'Tipo de bodega', 'Código', 'Producto', 'Categoría', 'Condición', 'Existencias', 'Disponibles', 'Actualización',
        ], $filas);
    }

    public function compras($consulta, ?string $busqueda)
    {
        $buscar = $this->normalizar($busqueda ?? '');
        $filas = (function () use ($consulta, $buscar) {
            foreach ($consulta->orderByDesc('c.id')->lazy(150)->chunk(150) as $grupo) {
                $compras = $grupo->filter(fn ($fila) => str_contains($this->normalizar(implode(' ', [
                    $fila->id, $fila->fecha_compra, $fila->no_factura, $fila->proveedor_nombre, $fila->proyecto,
                    number_format($fila->total_factura, 2),
                ])), $buscar))->collect();
                $detalles = DB::table('compra_detalles as d')->leftJoin('productos as p', 'p.codigo', '=', 'd.producto_codigo')
                    ->whereIn('d.compra_id', $compras->pluck('id'))->orderBy('d.id')
                    ->get(['d.*', 'p.nombre as producto'])->groupBy('compra_id');
                $vinculos = DB::table('compra_consolidado')->join('consolidados', 'consolidados.id', '=', 'compra_consolidado.consolidado_id')
                    ->whereIn('compra_id', $compras->pluck('id'))->orderBy('consolidados.id')
                    ->get(['compra_id', 'consolidados.id', 'nombre'])->groupBy('compra_id');
                foreach ($compras as $compra) {
                    $consolidados = $vinculos->get($compra->id, collect())->map(fn ($fila) => '#'.$fila->id.' '.$fila->nombre)->implode(', ');
                    foreach ($detalles->get($compra->id, [null]) as $linea) {
                        yield [$compra->id, Carbon::parse($compra->fecha_compra), $compra->no_factura, $compra->proveedor_nombre,
                            $linea?->producto_codigo, $linea?->producto, $linea ? (int) $linea->cantidad : null,
                            $linea ? (float) $linea->precio_unitario : null, $linea ? (float) $linea->valor_total : null,
                            (float) $compra->total_factura, $compra->autorizado_por, $consolidados,
                            !$consolidados ? null : ($compra->distribucion_confirmada_en ? 'Confirmada' : 'Pendiente de conciliación')];
                    }
                }
            }
        })();

        return $this->excel->descargar('compras', ['Compra', 'Fecha', 'Factura', 'Proveedor', 'Código de producto', 'Producto',
            'Cantidad', 'Precio unitario', 'Total de línea', 'Total de factura', 'Autorizado por', 'Consolidados vinculados', 'Distribución'], $filas);
    }

    public function asignaciones($consulta)
    {
        $filas = $consulta->setEagerLoads([])->with('colaborador', 'producto', 'bodega', 'user', 'entrega', 'movimientoInicial', 'requerimiento.participante')
            ->orderByDesc('id')->lazy(250)->map(fn ($fila) => [
                $fila->colaborador_codigo, $fila->colaborador?->nombre, $fila->producto_codigo, $fila->producto?->nombre,
                $fila->bodega?->nombre, (int) ($fila->movimientoInicial?->cantidad ?? $fila->cantidad_asignada),
                (int) $fila->cantidad_asignada, $fila->fecha, $fila->custodia_etiqueta, $fila->user?->name,
                $fila->requerimiento_id, $fila->requerimiento?->participante?->consolidado_id,
            ]);

        return $this->excel->descargar('asignaciones', ['Código de colaborador', 'Colaborador', 'Código de producto', 'Producto',
            'Bodega', 'Cantidad de salida', 'Cantidad en custodia', 'Fecha', 'Estado', 'Responsable', 'Requerimiento', 'Consolidado'], $filas);
    }

    public function devoluciones($consulta)
    {
        $filas = AsignacionMovimiento::with('user', 'asignacion.colaborador', 'asignacion.producto')
            ->where(fn ($q) => $q->where('tipo', 'Devolucion')->orWhereNotNull('grupo_devolucion'))->whereIn('asignacion_inventario_id', $consulta->reorder()->select('asignaciones_inventarios.id')->toBase())
            ->orderByDesc('id')->lazy(250)->map(fn ($fila) => [
                $fila->asignacion?->colaborador_codigo, $fila->asignacion?->colaborador?->nombre,
                $fila->asignacion?->producto_codigo, $fila->asignacion?->producto?->nombre, (int) $fila->cantidad,
                $fila->ocurrio_en ?? $fila->created_at, $fila->estado_devolucion, $fila->detalle, $fila->user?->name,
            ]);

        return $this->excel->descargar('devoluciones', ['Código de colaborador', 'Colaborador', 'Código de producto', 'Producto', 'Cantidad',
            'Fecha', 'Estado de devolución', 'Motivo', 'Responsable'], $filas);
    }

    public function traslados($consulta)
    {
        $filas = $consulta->with('detalles.producto')->orderByDesc('id')->lazy(150)->flatMap(fn ($fila) =>
            ($fila->detalles->isEmpty() ? collect([null]) : $fila->detalles)->map(fn ($detalle) => [
                $fila->id, $fila->bodegaOrigen?->nombre, $fila->bodegaDestino?->nombre,
                $detalle?->producto_codigo, $detalle?->producto?->nombre, $detalle ? (int) $detalle->cantidad : null,
                $fila->created_at, $fila->estado, $fila->creador?->name,
            ])->all());

        return $this->excel->descargar('traslados', ['Traslado', 'Origen', 'Destino', 'Código', 'Producto',
            'Cantidad', 'Fecha', 'Estado', 'Solicitante'], $filas);
    }

    public function inspecciones($consulta)
    {
        $filas = $consulta->with('lineas')->orderByDesc('id')->lazy(150)->flatMap(fn ($fila) =>
            ($fila->lineas->isEmpty() ? collect([null]) : $fila->lineas)->map(fn ($detalle) => [
                $fila->id, $fila->fecha, $fila->supervisor?->name, $fila->region?->nombre, $fila->colaborador_codigo,
                $fila->colaborador?->nombre, $detalle?->producto_codigo, $detalle?->etiqueta, $detalle ? (int) $detalle->cantidad : null,
                Consolidado::ESTADOS[$fila->consolidados->first()?->estado] ?? 'Recibido', $fila->observaciones,
            ])->all());

        return $this->excel->descargar('inspecciones', ['Inspección', 'Fecha', 'Supervisor', 'Región', 'Código de colaborador',
            'Colaborador', 'Código de producto', 'Producto', 'Cantidad original', 'Estado', 'Observaciones'], $filas);
    }

    public function consolidados($consulta)
    {
        $filas = (function () use ($consulta) {
            foreach ($consulta->with('participantes.colaborador', 'participantes.region', 'participantes.necesidades.producto',
                'inspecciones.region', 'inspecciones.colaborador', 'inspecciones.lineas', 'lineas.producto')->lazy(100) as $fila) {
                $base = [$fila->id, $fila->nombre, $fila->desde, $fila->hasta, $fila->coordinador?->name];
                $estado = Consolidado::ESTADOS[$fila->estado] ?? $fila->estado;
                $aprobado = in_array($fila->estado, ['aprobado', 'cerrado'], true);
                if ($fila->detalle_individual) {
                    foreach ($fila->participantes as $persona) {
                        foreach ($persona->necesidades->isEmpty() ? [null] : $persona->necesidades as $detalle) {
                            yield [...$base, $persona->region?->nombre, $persona->colaborador_codigo, $persona->colaborador?->nombre,
                                $detalle?->producto_codigo, $detalle?->producto?->nombre, $detalle?->original,
                                $aprobado ? $detalle?->cantidad : null, $detalle ? $detalle->cantidad - $detalle->original : null,
                                $estado, $aprobado ? 'Aprobado por colaborador' : 'Propuesta', $aprobado ? null : $detalle?->cantidad];
                        }
                    }
                    continue;
                }
                foreach ($fila->inspecciones as $inspeccion) {
                    foreach ($inspeccion->lineas->isEmpty() ? [null] : $inspeccion->lineas as $detalle) {
                        yield [...$base, $inspeccion->region?->nombre, $inspeccion->colaborador_codigo, $inspeccion->colaborador?->nombre,
                            $detalle?->producto_codigo, $detalle?->etiqueta, $detalle ? (int) $detalle->cantidad : null,
                            null, null, $estado, 'Inspección histórica sin distribución individual verificada'];
                    }
                }
                foreach ($fila->lineas->isEmpty() ? [null] : $fila->lineas as $detalle) {
                    yield [...$base, null, null, null, $detalle?->producto_codigo, $detalle?->producto?->nombre,
                        $detalle ? (int) $detalle->original : null, $aprobado && $detalle ? (int) $detalle->cantidad : null,
                        null, $estado, 'Total histórico por producto'];
                }
            }
        })();

        return $this->excel->descargar('consolidados', ['Consolidado', 'Período', 'Desde', 'Hasta', 'Coordinador', 'Región',
            'Código de colaborador', 'Colaborador', 'Código de producto', 'Producto', 'Cantidad original', 'Cantidad aprobada',
            'Diferencia', 'Estado', 'Fuente de la cantidad', 'Cantidad propuesta'], $filas);
    }

    public function necesidades($lineas, $seleccion, $facturas, $saldo)
    {
        $nombres = $seleccion->keyBy('id');
        $filas = (function () use ($lineas, $nombres, $facturas, $saldo) {
            foreach ($lineas->chunk(250) as $grupo) {
                $repartos = DB::table('compra_detalle_consolidado as d')
                    ->join('compra_detalles as linea', 'linea.id', '=', 'd.compra_detalle_id')
                    ->join('compras', 'compras.id', '=', 'linea.compra_id')
                    ->whereIn('d.consolidado_linea_id', $grupo->pluck('id'))->orderBy('compras.id')
                    ->get(['d.consolidado_linea_id', 'd.cantidad', 'compras.no_factura'])->groupBy('consolidado_linea_id');
                foreach ($grupo as $fila) {
                    $vinculadas = $repartos->get($fila['id'], collect())->map(fn ($r) => $r->no_factura.' ('.$r->cantidad.')')->implode(', ');
                    $historicas = $facturas->get($fila['consolidado_id'], collect())->whereNull('distribucion_confirmada_en')->pluck('no_factura')->implode(', ');
                    yield [$fila['consolidado_id'], $nombres->get($fila['consolidado_id'])->nombre, $fila['codigo'], $fila['producto'],
                        $fila['aprobada'], $fila['considerada'], $saldo ? (int) $saldo->get($fila['codigo'], 0) : null,
                        $fila['decidida'], $fila['comprada'], $fila['pendiente'], $vinculadas, $historicas];
                }
            }
        })();

        return $this->excel->descargar('necesidades-de-compra', ['Consolidado', 'Período', 'Código', 'Producto',
            'Cantidad aprobada', 'Existencias consideradas', 'Existencias actuales', 'Compra decidida', 'Cantidad comprada',
            'Cantidad pendiente', 'Facturas distribuidas (unidades)', 'Facturas del consolidado por conciliar'], $filas);
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(preg_replace('/\p{Mn}/u', '', \Normalizer::normalize(trim($texto), \Normalizer::FORM_D)));
    }
}
