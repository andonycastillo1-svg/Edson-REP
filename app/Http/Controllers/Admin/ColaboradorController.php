<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertaReemplazo;
use App\Models\Colaborador;
use App\Models\AsignacionInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ColaboradorController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $activos = Colaborador::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('codigo', 'like', "%{$q}%")
                        ->orWhere('nombre', 'like', "%{$q}%")
                        ->orWhere('puesto', 'like', "%{$q}%");
                });
            })
            ->where('estado', 'Activo')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $inactivos = Colaborador::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('codigo', 'like', "%{$q}%")
                        ->orWhere('nombre', 'like', "%{$q}%")
                        ->orWhere('puesto', 'like', "%{$q}%");
                });
            })
            ->where('estado', 'Inactivo')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('admin.colaboradores.index', compact('activos', 'inactivos'));
    }

    public function create()
    {
        return view('admin.colaboradores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        Colaborador::create($data);

        return redirect()
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
            ->with('success', 'Colaborador creado correctamente.');
    }

    public function edit(Colaborador $colaborador)
    {
        return view('admin.colaboradores.edit', compact('colaborador'));
    }

    public function update(Request $request, Colaborador $colaborador)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo,' . $colaborador->codigo . ',codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        $colaborador->update($data);

        return redirect()
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    public function detalle(Colaborador $colaborador)
    {
        $ficha = $this->generarFichaColaborador($colaborador);

        return response()->json([
            'colaborador' => $ficha['colaborador'],

            'asignaciones' => $ficha['asignaciones'],
            'total_inventario' => $ficha['total_inventario'],

            'vehiculo_asignado' => $ficha['vehiculo_asignado'],
            'productos_vehiculo' => $ficha['productos_vehiculo'],
            'total_productos_vehiculo' => $ficha['total_productos_vehiculo'],

            'total_general' => $ficha['total_general'],

            'cobros' => $ficha['cobros'],
            'total_cobros' => $ficha['total_cobros'],
        ]);
    }

    public function fichaTecnica(Colaborador $colaborador)
    {
        $ficha = $this->generarFichaColaborador($colaborador);

        $filename = 'ficha_colaborador_' . $colaborador->codigo . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($ficha) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $this->headersFichaTecnicaCsv());
            $this->escribirFilasFichaTecnicaCsv($out, $ficha);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function fichasTecnicasMasivas(Request $request)
    {
        $data = $request->validate([
            'codigos' => ['required', 'array', 'min:1'],
            'codigos.*' => ['required', 'exists:colaboradores,codigo'],
        ], [
            'codigos.required' => 'Debe seleccionar al menos un colaborador.',
            'codigos.min' => 'Debe seleccionar al menos un colaborador.',
        ]);

        $colaboradores = Colaborador::whereIn('codigo', $data['codigos'])
            ->orderBy('nombre')
            ->get();

        if ($colaboradores->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No se encontraron colaboradores para descargar.');
        }

        $filename = 'fichas_colaboradores_seleccionados_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($colaboradores) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $this->headersFichaTecnicaCsv());

            foreach ($colaboradores as $colaborador) {
                $ficha = $this->generarFichaColaborador($colaborador);
                $this->escribirFilasFichaTecnicaCsv($out, $ficha);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function headersFichaTecnicaCsv(): array
    {
        return [
            'Tipo registro',
            'Código colaborador',
            'Colaborador',
            'Puesto',
            'Estado colaborador',

            'Código producto',
            'Producto',
            'Bodega',
            'Cantidad',
            'Costo unitario',
            'Total',

            'Vehículo',
            'Marca',
            'Modelo',
            'Placa',
            'VIN',

            'Fecha asignación',
            'Fecha vencimiento',
            'Estado vida útil',

            'Motivo',
            'Aplica cobro',
            'Monto cobro',
            'Estado cobro',
            'Detalle',
        ];
    }

    private function escribirFilasFichaTecnicaCsv($out, array $ficha): void
    {
        $colaborador = $ficha['colaborador'];

        if (count($ficha['asignaciones']) > 0) {
            foreach ($ficha['asignaciones'] as $item) {
                fputcsv($out, [
                    'Inventario directo',
                    $colaborador['codigo'],
                    $colaborador['nombre'],
                    $colaborador['puesto'],
                    $colaborador['estado'],

                    $item['producto_codigo'] ?? '',
                    $item['producto'] ?? '',
                    $item['bodega'] ?? '',
                    $item['cantidad'] ?? 0,
                    number_format((float) ($item['costo_unitario'] ?? 0), 2, '.', ''),
                    number_format((float) ($item['total'] ?? 0), 2, '.', ''),

                    '',
                    '',
                    '',
                    '',
                    '',

                    $item['fecha_asignacion'] ?? '',
                    $item['fecha_vencimiento'] ?? '',
                    $item['estado_vida_util'] ?? '',

                    '',
                    '',
                    '',
                    '',
                    '',
                ]);
            }
        } else {
            fputcsv($out, [
                'Inventario directo',
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],

                '',
                'Sin productos asignados directamente',
                '',
                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',

                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',
            ]);
        }

        if ($ficha['vehiculo_asignado']) {
            $vehiculo = $ficha['vehiculo_asignado'];

            fputcsv($out, [
                'Vehículo asignado',
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],

                '',
                '',
                '',
                '',
                '',
                number_format((float) ($ficha['total_productos_vehiculo'] ?? 0), 2, '.', ''),

                trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['modelo'] ?? '')),
                $vehiculo['marca'] ?? '',
                $vehiculo['modelo'] ?? '',
                $vehiculo['placa'] ?? '',
                $vehiculo['vin'] ?? '',

                $vehiculo['fecha_asignacion'] ?? '',
                '',
                $vehiculo['estado'] ?? '',

                '',
                '',
                '',
                '',
                '',
            ]);
        } else {
            fputcsv($out, [
                'Vehículo asignado',
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],

                '',
                '',
                '',
                '',
                '',
                '',

                'Sin vehículo asignado',
                '',
                '',
                '',
                '',

                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',
            ]);
        }

        if (count($ficha['productos_vehiculo']) > 0) {
            foreach ($ficha['productos_vehiculo'] as $item) {
                $vehiculo = $ficha['vehiculo_asignado'];

                fputcsv($out, [
                    'Producto vehículo',
                    $colaborador['codigo'],
                    $colaborador['nombre'],
                    $colaborador['puesto'],
                    $colaborador['estado'],

                    $item['producto_codigo'] ?? '',
                    $item['producto'] ?? '',
                    $item['bodega'] ?? '',
                    $item['cantidad'] ?? 0,
                    number_format((float) ($item['costo_unitario'] ?? 0), 2, '.', ''),
                    number_format((float) ($item['total'] ?? 0), 2, '.', ''),

                    $vehiculo ? trim(($vehiculo['marca'] ?? '') . ' ' . ($vehiculo['modelo'] ?? '')) : '',
                    $vehiculo['marca'] ?? '',
                    $vehiculo['modelo'] ?? '',
                    $vehiculo['placa'] ?? '',
                    $vehiculo['vin'] ?? '',

                    $item['fecha'] ?? '',
                    '',
                    $item['estado'] ?? '',

                    $item['motivo'] ?? '',
                    '',
                    '',
                    '',
                    $item['observaciones'] ?? '',
                ]);
            }
        } else {
            fputcsv($out, [
                'Producto vehículo',
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],

                '',
                'Sin productos/refacciones en vehículo',
                '',
                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',

                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',
            ]);
        }

        if (count($ficha['cobros']) > 0) {
            foreach ($ficha['cobros'] as $cobro) {
                fputcsv($out, [
                    'Cobro / descuento RRHH',
                    $colaborador['codigo'],
                    $colaborador['nombre'],
                    $colaborador['puesto'],
                    $colaborador['estado'],

                    $cobro['producto_codigo'] ?? '',
                    $cobro['producto'] ?? '',
                    '',
                    '',
                    number_format((float) ($cobro['costo_producto'] ?? 0), 2, '.', ''),
                    '',

                    '',
                    '',
                    '',
                    '',
                    '',

                    $cobro['fecha_asignacion_anterior'] ?? '',
                    $cobro['fecha_dano_reemplazo'] ?? '',
                    'Vida útil: ' . ($cobro['vida_util_meses'] ?? 0) . ' meses / Restante: ' . ($cobro['meses_restantes'] ?? 0) . ' meses',

                    '',
                    !empty($cobro['descuento_aplicable']) ? 'Sí' : 'No',
                    number_format((float) ($cobro['monto_cobro'] ?? 0), 2, '.', ''),
                    $cobro['estado'] ?? '',
                    $cobro['detalle'] ?? '',
                ]);
            }
        } else {
            fputcsv($out, [
                'Cobro / descuento RRHH',
                $colaborador['codigo'],
                $colaborador['nombre'],
                $colaborador['puesto'],
                $colaborador['estado'],

                '',
                'Sin cobros o descuentos registrados',
                '',
                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',

                '',
                '',
                '',

                '',
                '',
                '',
                '',
                '',
            ]);
        }

        fputcsv($out, [
            'TOTAL INVENTARIO DIRECTO',
            $colaborador['codigo'],
            $colaborador['nombre'],
            $colaborador['puesto'],
            $colaborador['estado'],

            '',
            '',
            '',
            '',
            '',
            number_format((float) ($ficha['total_inventario'] ?? 0), 2, '.', ''),

            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',
        ]);

        fputcsv($out, [
            'TOTAL PRODUCTOS VEHÍCULO',
            $colaborador['codigo'],
            $colaborador['nombre'],
            $colaborador['puesto'],
            $colaborador['estado'],

            '',
            '',
            '',
            '',
            '',
            number_format((float) ($ficha['total_productos_vehiculo'] ?? 0), 2, '.', ''),

            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',
        ]);

        fputcsv($out, [
            'TOTAL GENERAL ASIGNADO',
            $colaborador['codigo'],
            $colaborador['nombre'],
            $colaborador['puesto'],
            $colaborador['estado'],

            '',
            '',
            '',
            '',
            '',
            number_format((float) ($ficha['total_general'] ?? 0), 2, '.', ''),

            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',
        ]);

        fputcsv($out, [
            'TOTAL COBROS / DESCUENTOS',
            $colaborador['codigo'],
            $colaborador['nombre'],
            $colaborador['puesto'],
            $colaborador['estado'],

            '',
            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',
            '',
            '',

            '',
            '',
            '',

            '',
            '',
            number_format((float) ($ficha['total_cobros'] ?? 0), 2, '.', ''),
            '',
            '',
        ]);
    }

    private function generarFichaColaborador(Colaborador $colaborador): array
    {
        $asignaciones = AsignacionInventario::with('producto', 'bodega')
            ->where('colaborador_codigo', $colaborador->codigo)
            ->where('cantidad_asignada', '>', 0)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', 'Activa');
            })
            ->get();

        $codigosProducto = $asignaciones->pluck('producto_codigo')->filter()->unique()->values();
        $ultimosCostos = $this->obtenerUltimosCostos($codigosProducto);

        $totalInventario = 0;

        $dataAsignaciones = $asignaciones->map(function ($a) use (&$totalInventario, $ultimosCostos) {
            $costoUnitario = $a->costo_unitario ?? 0;

            if ((float) $costoUnitario <= 0 && isset($ultimosCostos[$a->producto_codigo])) {
                $costoUnitario = (float) $ultimosCostos[$a->producto_codigo];
            }

            $total = (float) $costoUnitario * (int) $a->cantidad_asignada;
            $fechaAsignacion = $a->fecha ? Carbon::parse($a->fecha) : null;
            $fechaVencimiento = $a->fecha_vencimiento ? Carbon::parse($a->fecha_vencimiento) : null;

            $estadoVidaUtil = 'Sin vida útil';

            if ($fechaVencimiento) {
                $estadoVidaUtil = now()->greaterThan($fechaVencimiento) ? 'Vencido' : 'Vigente';
            }

            $totalInventario += $total;

            return [
                'producto' => $a->producto->nombre ?? '—',
                'producto_codigo' => $a->producto_codigo,
                'bodega' => $a->bodega->nombre ?? '—',
                'cantidad' => $a->cantidad_asignada,
                'costo_unitario' => (float) $costoUnitario,
                'total' => (float) $total,
                'fecha_asignacion' => $fechaAsignacion?->format('d/m/Y'),
                'fecha_vencimiento' => $fechaVencimiento?->format('d/m/Y'),
                'estado_vida_util' => $estadoVidaUtil,
            ];
        })->values();

        $vehiculoAsignado = $this->obtenerVehiculoAsignado($colaborador);
        $productosVehiculo = collect();
        $totalProductosVehiculo = 0;

        if ($vehiculoAsignado && !empty($vehiculoAsignado['vin'])) {
            $productosVehiculo = $this->obtenerProductosVehiculo($vehiculoAsignado['vin']);
            $totalProductosVehiculo = (float) $productosVehiculo->sum('total');
        }

        $cobros = collect();
        $totalCobros = 0;

        if (Schema::hasTable('alertas_reemplazos_rrhh')) {
            $cobros = AlertaReemplazo::query()
                ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
                ->where('alertas_reemplazos_rrhh.colaborador_codigo', $colaborador->codigo)
                ->addSelect('alertas_reemplazos_rrhh.*')
                ->addSelect('p.nombre as producto_nombre')
                ->addSelect('p.descripcion as producto_descripcion')
                ->selectRaw("
                    COALESCE(
                        (
                            SELECT cd.precio_unitario
                            FROM compra_detalles cd
                            WHERE cd.producto_codigo = alertas_reemplazos_rrhh.producto_codigo
                            ORDER BY cd.id DESC
                            LIMIT 1
                        ),
                        0
                    ) as costo_producto
                ")
                ->latest('alertas_reemplazos_rrhh.created_at')
                ->get()
                ->map(function ($alerta) use (&$totalCobros) {
                    $vidaUtilMeses = (int) ($alerta->vida_util_meses ?? 0);
                    $mesesRestantes = max(0, (int) ($alerta->meses_restantes ?? 0));
                    $costoProducto = (float) ($alerta->costo_producto ?? 0);

                    $montoCobro = ($alerta->descuento_aplicable && $vidaUtilMeses > 0)
                        ? round($costoProducto * ($mesesRestantes / $vidaUtilMeses), 2)
                        : 0;

                    $totalCobros += $montoCobro;

                    return [
                        'producto_codigo' => $alerta->producto_codigo,
                        'producto' => $alerta->producto_descripcion ?: ($alerta->producto_nombre ?: $alerta->producto_codigo),
                        'fecha_asignacion_anterior' => $alerta->fecha_asignacion_anterior
                            ? Carbon::parse($alerta->fecha_asignacion_anterior)->format('d/m/Y')
                            : '—',
                        'fecha_dano_reemplazo' => $alerta->fecha_dano_reemplazo
                            ? Carbon::parse($alerta->fecha_dano_reemplazo)->format('d/m/Y')
                            : '—',
                        'vida_util_meses' => $vidaUtilMeses,
                        'meses_restantes' => $mesesRestantes,
                        'costo_producto' => $costoProducto,
                        'descuento_aplicable' => (bool) $alerta->descuento_aplicable,
                        'monto_cobro' => $montoCobro,
                        'estado' => $alerta->estado ?? 'pendiente',
                        'detalle' => $alerta->detalle ?? '—',
                    ];
                })
                ->values();
        }

        return [
            'colaborador' => [
                'codigo' => $colaborador->codigo,
                'nombre' => $colaborador->nombre,
                'puesto' => $colaborador->puesto,
                'estado' => $colaborador->estado,
            ],

            'asignaciones' => $dataAsignaciones,
            'total_inventario' => (float) $totalInventario,

            'vehiculo_asignado' => $vehiculoAsignado,
            'productos_vehiculo' => $productosVehiculo->values(),
            'total_productos_vehiculo' => (float) $totalProductosVehiculo,

            'total_general' => (float) ($totalInventario + $totalProductosVehiculo),

            'cobros' => $cobros,
            'total_cobros' => (float) $totalCobros,
        ];
    }

    private function obtenerVehiculoAsignado(Colaborador $colaborador): ?array
    {
        $tablaAsignaciones = $this->detectarTabla([
            'vehiculo_asignaciones',
            'vehiculos_asignaciones',
            'asignaciones_vehiculos',
            'asignacion_vehiculos',
        ]);

        if (!$tablaAsignaciones || !Schema::hasTable('vehiculos')) {
            return null;
        }

        if (!Schema::hasColumn($tablaAsignaciones, 'colaborador_codigo')) {
            return null;
        }

        $query = DB::table($tablaAsignaciones . ' as av')
            ->leftJoin('vehiculos as v', 'v.vin', '=', 'av.vehiculo_vin')
            ->where('av.colaborador_codigo', $colaborador->codigo);

        if (Schema::hasColumn($tablaAsignaciones, 'activa')) {
            $query->where('av.activa', 1);
        } elseif (Schema::hasColumn($tablaAsignaciones, 'estado')) {
            $query->where(function ($q) {
                $q->where('av.estado', 'Activa')
                    ->orWhere('av.estado', 'Activo')
                    ->orWhere('av.estado', 'activa')
                    ->orWhere('av.estado', 'activo');
            });
        } elseif (Schema::hasColumn($tablaAsignaciones, 'fecha_fin')) {
            $query->whereNull('av.fecha_fin');
        }

        if (Schema::hasColumn($tablaAsignaciones, 'fecha_inicio')) {
            $query->orderByDesc('av.fecha_inicio');
        } else {
            $query->orderByDesc('av.id');
        }

        $row = $query->select([
            'av.*',
            'v.marca as vehiculo_marca',
            'v.modelo as vehiculo_modelo',
            'v.placa as vehiculo_placa',
            'v.vin as vehiculo_vin_real',
        ])->first();

        if (!$row) {
            return null;
        }

        $fechaAsignacion = null;

        if (!empty($row->fecha_inicio)) {
            $fechaAsignacion = Carbon::parse($row->fecha_inicio)->format('d/m/Y');
        } elseif (!empty($row->fecha_asignacion)) {
            $fechaAsignacion = Carbon::parse($row->fecha_asignacion)->format('d/m/Y');
        } elseif (!empty($row->created_at)) {
            $fechaAsignacion = Carbon::parse($row->created_at)->format('d/m/Y');
        }

        return [
            'id' => $row->id ?? null,
            'vin' => $row->vehiculo_vin ?? $row->vehiculo_vin_real ?? null,
            'marca' => $row->vehiculo_marca ?? '—',
            'modelo' => $row->vehiculo_modelo ?? '—',
            'placa' => $row->vehiculo_placa ?? '—',
            'fecha_asignacion' => $fechaAsignacion ?? '—',
            'estado' => $row->estado ?? (!empty($row->activa) ? 'Activa' : '—'),
        ];
    }

    private function obtenerProductosVehiculo(string $vehiculoVin)
    {
        $tablaProductosVehiculo = $this->detectarTabla([
            'vehiculo_productos',
            'vehiculos_productos',
            'vehiculo_producto_asignaciones',
            'asignacion_vehiculo_productos',
            'asignaciones_vehiculo_productos',
        ]);

        if (!$tablaProductosVehiculo) {
            return collect();
        }

        if (!Schema::hasColumn($tablaProductosVehiculo, 'vehiculo_vin')) {
            return collect();
        }

        $query = DB::table($tablaProductosVehiculo . ' as vp')
            ->leftJoin('productos as p', 'p.codigo', '=', 'vp.producto_codigo')
            ->leftJoin('bodegas as b', 'b.id', '=', 'vp.bodega_id')
            ->where('vp.vehiculo_vin', $vehiculoVin);

        if (Schema::hasColumn($tablaProductosVehiculo, 'activa')) {
            $query->where('vp.activa', 1);
        } elseif (Schema::hasColumn($tablaProductosVehiculo, 'estado')) {
            $query->where(function ($q) {
                $q->where('vp.estado', 'Activo')
                    ->orWhere('vp.estado', 'Activa')
                    ->orWhere('vp.estado', 'activo')
                    ->orWhere('vp.estado', 'activa');
            });
        }

        $rows = $query
            ->select([
                'vp.*',
                'p.nombre as producto_nombre',
                'p.descripcion as producto_descripcion',
                'b.nombre as bodega_nombre',
            ])
            ->orderByDesc(Schema::hasColumn($tablaProductosVehiculo, 'fecha') ? 'vp.fecha' : 'vp.id')
            ->get();

        $codigosProducto = $rows->pluck('producto_codigo')->filter()->unique()->values();
        $ultimosCostos = $this->obtenerUltimosCostos($codigosProducto);

        return $rows->map(function ($row) use ($ultimosCostos) {
            $cantidad = (int) ($row->cantidad ?? 1);

            $costoUnitario = 0;

            if (isset($row->costo_unitario)) {
                $costoUnitario = (float) $row->costo_unitario;
            }

            if ($costoUnitario <= 0 && isset($ultimosCostos[$row->producto_codigo])) {
                $costoUnitario = (float) $ultimosCostos[$row->producto_codigo];
            }

            $total = $cantidad * $costoUnitario;

            $fecha = null;

            if (!empty($row->fecha)) {
                $fecha = Carbon::parse($row->fecha)->format('d/m/Y');
            } elseif (!empty($row->created_at)) {
                $fecha = Carbon::parse($row->created_at)->format('d/m/Y');
            }

            return [
                'id' => $row->id ?? null,
                'producto_codigo' => $row->producto_codigo ?? '—',
                'producto' => $row->producto_descripcion ?: ($row->producto_nombre ?: ($row->producto_codigo ?? '—')),
                'bodega' => $row->bodega_nombre ?? '—',
                'cantidad' => $cantidad,
                'costo_unitario' => (float) $costoUnitario,
                'total' => (float) $total,
                'fecha' => $fecha ?? '—',
                'motivo' => $row->motivo ?? '—',
                'estado' => $row->estado ?? (!empty($row->activa) ? 'Activo' : '—'),
                'observaciones' => $row->observaciones ?? '—',
            ];
        })->values();
    }

    private function obtenerUltimosCostos($codigosProducto)
    {
        $codigosProducto = collect($codigosProducto)->filter()->unique()->values();

        if ($codigosProducto->isEmpty()) {
            return collect();
        }

        if (!Schema::hasTable('compra_detalles')) {
            return collect();
        }

        $query = DB::table('compra_detalles as cd')
            ->whereIn('cd.producto_codigo', $codigosProducto);

        if (Schema::hasTable('compras') && Schema::hasColumn('compra_detalles', 'compra_id')) {
            $query->leftJoin('compras as c', 'c.id', '=', 'cd.compra_id');

            if (Schema::hasColumn('compras', 'fecha_compra')) {
                $query->orderByDesc('c.fecha_compra');
            }
        }

        $query->orderByDesc('cd.id');

        return $query
            ->get(['cd.producto_codigo', 'cd.precio_unitario'])
            ->unique('producto_codigo')
            ->pluck('precio_unitario', 'producto_codigo');
    }

    private function detectarTabla(array $posiblesTablas): ?string
    {
        foreach ($posiblesTablas as $tabla) {
            if (Schema::hasTable($tabla)) {
                return $tabla;
            }
        }

        return null;
    }

    public function cambiarEstado(Colaborador $colaborador)
    {
        $nuevoEstado = $colaborador->estado === 'Activo' ? 'Inactivo' : 'Activo';

        $colaborador->update([
            'estado' => $nuevoEstado,
        ]);

        return redirect()
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
            ->with('success', 'Estado del colaborador actualizado correctamente.');
    }

    private function colaboradoresRoutePrefix(): string
    {
        return auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
    }
}