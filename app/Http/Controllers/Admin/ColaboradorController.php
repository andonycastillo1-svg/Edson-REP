<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Exports\ColaboradoresFichaExport;
use App\Http\Controllers\Controller;
use App\Sistema\ConsultaCampos;
use App\Sistema\Presentacion;
use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\Colaborador;
use App\Models\IncidenciaInventario;
use App\Services\AsignacionVidaUtilService;
use App\Sistema\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class ColaboradorController extends Controller
{
    public function index(Request $request)
    {
        $consulta = Colaborador::query();
        $campos = app(ConsultaCampos::class);
        $campos->buscar($consulta, $request->validate(['q' => ['nullable', 'string', 'max:190']])['q'] ?? null, ['codigo', 'nombre', 'puesto']);
        $campos->filtrar($consulta, $request, 'colaboradores');

        $activos = (clone $consulta)->where('estado', 'Activo')->orderBy('nombre')->paginate(10)->withQueryString();
        $inactivos = (clone $consulta)->where('estado', 'Inactivo')->orderBy('nombre')->paginate(10)->withQueryString();
        $codigos = $activos->pluck('codigo')->merge($inactivos->pluck('codigo'));
        $tablaCampos = app(Presentacion::class)->operativa('colaboradores', $request->user(), $codigos);

        return view('admin.colaboradores.index', compact('activos', 'inactivos', 'tablaCampos'));
    }

    public function create()
    {
        return view('admin.colaboradores.create', ['regiones' => \App\Models\Region::where('activa', true)->orderBy('nombre')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'region_id' => ['nullable', 'integer', Rule::exists('regiones', 'id')->where('activa', true)],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'colaboradores', true);
        DB::transaction(function () use ($data, $campos, $valores) {
            $campos->verificarClaveDisponible('colaboradores', $data['codigo']);
            $colaborador = Colaborador::create($data);
            $campos->guardar('colaboradores', $colaborador->codigo, $valores);
        });

        return redirect()
            ->route($this->colaboradoresRoutePrefix().'.colaboradores.index')
            ->with('success', 'Colaborador creado correctamente.');
    }

    public function edit(Colaborador $colaborador)
    {
        $regiones = \App\Models\Region::where('activa', true)->orWhere('id', $colaborador->region_id)->orderBy('nombre')->get();

        return view('admin.colaboradores.edit', compact('colaborador', 'regiones'));
    }

    public function update(Request $request, Colaborador $colaborador)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo,'.$colaborador->codigo.',codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'region_id' => ['nullable', 'integer', Rule::exists('regiones', 'id')->where(fn ($q) => $q
                ->where(fn ($region) => $region->where('activa', true)->orWhere('id', $colaborador->region_id)))],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        $campos = app(ValoresCampos::class);
        DB::transaction(function () use ($request, $data, $campos, $colaborador) {
            $colaborador = Colaborador::whereKey($colaborador->codigo)->lockForUpdate()->firstOrFail();
            $anterior = $colaborador->codigo;
            $valores = $campos->preparar($request, 'colaboradores', false, $anterior);
            if ($anterior !== $data['codigo']) {
                $campos->verificarClaveDisponible('colaboradores', $data['codigo']);
            }
            $colaborador->update($data);
            $campos->trasladarClave('colaboradores', $anterior, $colaborador->codigo);
            $campos->guardar('colaboradores', $colaborador->codigo, $valores);
        });

        return redirect()
            ->route($this->colaboradoresRoutePrefix().'.colaboradores.index')
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

            'incidencias' => $ficha['incidencias'],
        ]);
    }

    public function fichaTecnica(Request $request, Colaborador $colaborador)
    {
        $ficha = $this->generarFichaColaborador($colaborador);
        $filename = 'ficha_colaborador_'.$colaborador->codigo.'_'.now()->format('Y-m-d_Hi').'.xlsx';

        return Excel::download(
            new ColaboradoresFichaExport(
                [$ficha],
                $request->user()->name,
                ['Colaborador' => $colaborador->codigo.' - '.$colaborador->nombre]
            ),
            $filename
        );
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

        $fichas = $colaboradores
            ->map(fn (Colaborador $colaborador) => $this->generarFichaColaborador($colaborador))
            ->all();
        $filename = 'fichas_colaboradores_seleccionados_'.now()->format('Y-m-d_Hi').'.xlsx';

        return Excel::download(
            new ColaboradoresFichaExport(
                $fichas,
                $request->user()->name,
                ['Colaboradores seleccionados' => $colaboradores->count()]
            ),
            $filename
        );
    }

    private function generarFichaColaborador(Colaborador $colaborador): array
    {
        $usuario = auth()->user();
        $permisos = app(Permisos::class);
        $puedeRevisar = $permisos->ruta($usuario, 'rrhh.alertas.show');
        $puedeConsultarInventario = $permisos->ruta($usuario, 'admin.asignaciones.index');
        $puedeHistorial = $puedeConsultarInventario || $puedeRevisar;
        $puedeAbrirArchivos = $permisos->ruta($usuario, 'admin.asignaciones.ver_pdf_firmado');
        $consulta = AsignacionInventario::with('producto', 'bodega', 'entrega')
            ->where('colaborador_codigo', $colaborador->codigo)->latest('id');
        if ($puedeHistorial) {
            $consulta->with('movimientos.user', 'movimientoInicial', 'incidencias.registrador', 'entrega.documentos', 'evidencias');
        } else {
            $consulta->custodiaAbierta();
        }
        $asignaciones = $consulta->get();

        if (config('inventario.seguimiento_unidades')) {
            $asignaciones->load('periodosActivos');
        }
        $codigosProducto = $asignaciones->pluck('producto_codigo')->filter()->unique()->values();
        $ultimosCostos = $this->obtenerUltimosCostos($codigosProducto);

        $totalInventario = 0;

        $vida = app(AsignacionVidaUtilService::class);
        $dataAsignaciones = $asignaciones->map(function ($a) use (&$totalInventario, $ultimosCostos, $vida, $puedeHistorial, $puedeAbrirArchivos) {
            $costoUnitario = $a->costo_unitario ?? 0;

            if ($a->entrega === null && (float) $costoUnitario <= 0 && isset($ultimosCostos[$a->producto_codigo])) {
                $costoUnitario = (float) $ultimosCostos[$a->producto_codigo];
            }

            $total = (float) $costoUnitario * (int) $a->cantidad_asignada;
            $fechaAsignacion = $a->fecha ? Carbon::parse($a->fecha) : null;
            $fechaVencimiento = $a->fecha_vencimiento ? Carbon::parse($a->fecha_vencimiento) : null;

            $retornos = $puedeHistorial ? $a->movimientos->whereIn('tipo', ['Devolucion', 'Reemplazo']) : collect();
            $incidencias = $puedeHistorial ? $a->incidencias : collect();
            $segundos = $a->estado === 'Activa' ? $vida->restante($a, now())
                : ($retornos->sortByDesc('id')->first()?->vida_restante_segundos ?? $incidencias->sortByDesc('id')->first()?->vida_restante_segundos);
            $documentos = $puedeHistorial ? ($a->entrega?->documentos ?? $a->evidencias) : collect();
            $estadoVidaUtil = match (true) {
                $segundos === null => 'Desconocida',
                $segundos > 0 => 'Vigente',
                default => 'Vencida',
            };

            $totalInventario += $total;

            return [
                'producto' => $a->producto->nombre ?? '—',
                'producto_codigo' => $a->producto_codigo,
                'bodega' => $a->bodega->nombre ?? '—',
                'cantidad' => $a->cantidad_asignada,
                'cantidad_entregada' => $puedeHistorial
                    ? ($a->movimientoInicial?->cantidad ?? $a->cantidad_asignada + $retornos->sum('cantidad')
                        + $incidencias->where('resultado_stock', 'sin_retorno')->sum('cantidad'))
                    : $a->cantidad_asignada,
                'custodia' => $a->custodia_etiqueta,
                'tipo_entrega' => $a->asignacion_anterior_id ? 'Nueva entrega del mismo código' : 'Asignación inicial',
                'motivo_registrado' => $a->motivo_registrado,
                'condicion_salida' => $a->stock_tipo === 'usado' ? 'Reutilizado' : ($a->stock_tipo === 'nuevo' ? 'Nuevo' : 'No registrada'),
                'retornos' => $retornos->map(fn ($movimiento) => [
                    'condicion' => \App\Services\ResolverCustodia::CONDICIONES[$movimiento->estado_devolucion] ?? 'Retorno físico',
                    'fecha' => ($movimiento->ocurrio_en ?? $movimiento->created_at)?->format('d/m/Y H:i'),
                    'cantidad' => $movimiento->cantidad, 'responsable' => $movimiento->user?->name,
                ])->values(),
                'incidencias' => $incidencias->map(fn ($incidencia) => [
                    'condicion' => $incidencia->motivo_etiqueta, 'fecha' => $incidencia->ocurrio_en?->format('d/m/Y H:i'),
                    'cantidad' => $incidencia->cantidad, 'responsable' => $incidencia->registrador?->name,
                ])->values(),
                'documentos' => $documentos->map(fn ($archivo) => [
                    'nombre' => $puedeAbrirArchivos ? $archivo->nombre_original : null,
                    'tipo' => $archivo->tipo_documento === 'asignacion_firmada' ? 'Hoja firmada' : 'Fotografía',
                    'url' => $puedeAbrirArchivos ? route('admin.asignaciones.ver_pdf_firmado', $archivo) : null,
                ])->values(),
                'costo_unitario' => (float) $costoUnitario,
                'total' => (float) $total,
                'fecha_asignacion' => ($a->entrega?->salida_en ?? $fechaAsignacion)?->format('d/m/Y H:i'),
                'fecha_vencimiento' => $fechaVencimiento?->format('d/m/Y'),
                'estado_vida_util' => $estadoVidaUtil,
            ];
        })->values();

        $vehiculoAsignado = $this->obtenerVehiculoAsignado($colaborador);
        $productosVehiculo = collect();
        $totalProductosVehiculo = 0;

        if ($vehiculoAsignado && ! empty($vehiculoAsignado['vin'])) {
            $productosVehiculo = $this->obtenerProductosVehiculo($vehiculoAsignado['vin']);
            $totalProductosVehiculo = (float) $productosVehiculo->sum('total');
        }

        $incidencias = collect();
        if ($puedeRevisar || $usuario->rolOperativo() === 1) {
            $incidencias = AlertaReemplazo::with(AlertaReemplazo::RELACIONES_LISTADO)
                ->where('colaborador_codigo', $colaborador->codigo)->latest()->get()
                ->map(fn (AlertaReemplazo $alerta) => [
                    'producto_codigo' => $alerta->producto_codigo,
                    'producto' => $alerta->producto?->nombre ?? $alerta->producto_codigo,
                    'bodega' => $alerta->bodega_nombre,
                    'cantidad' => $alerta->cantidad,
                    'fecha_asignacion' => $alerta->fecha_asignacion_anterior?->format('d/m/Y'),
                    'fecha_incidente' => $alerta->fecha_incidente?->format('d/m/Y H:i'),
                    'vida' => $alerta->vida_etiqueta,
                    'motivo' => $alerta->motivo_etiqueta,
                    'estado' => $alerta->estado_etiqueta,
                    'registrador' => $alerta->registrador?->name,
                    'detalle' => $alerta->detalle,
                    'url' => $puedeRevisar ? route('rrhh.alertas.show', $alerta) : null,
                ]);
        }
        if ($usuario->rolOperativo() === 1 && $permisos->permite($usuario, 'asignaciones.consultar')) {
            $sinAlerta = IncidenciaInventario::with('asignacion.bodega', 'asignacion.producto', 'registrador')
                ->whereDoesntHave('alerta')->whereHas('asignacion', fn ($q) => $q->where('colaborador_codigo', $colaborador->codigo))
                ->latest()->get()->map(fn (IncidenciaInventario $incidencia) => [
                    'producto_codigo' => $incidencia->asignacion->producto_codigo,
                    'producto' => $incidencia->asignacion->producto?->nombre,
                    'bodega' => $incidencia->asignacion->bodega?->nombre,
                    'cantidad' => $incidencia->cantidad,
                    'fecha_asignacion' => $incidencia->asignacion->fecha?->format('d/m/Y'),
                    'fecha_incidente' => $incidencia->ocurrio_en?->format('d/m/Y H:i'),
                    'vida' => ucfirst($incidencia->estado_vida),
                    'motivo' => $incidencia->motivo_etiqueta,
                    'estado' => 'Registrada en inventario',
                    'registrador' => $incidencia->registrador?->name,
                    'detalle' => $incidencia->observaciones,
                    'url' => route('admin.asignaciones.incidencia', $incidencia),
                ]);
            $incidencias = $incidencias->concat($sinAlerta);
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

            'incidencias' => $incidencias,
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

        if (! $tablaAsignaciones || ! Schema::hasTable('vehiculos')) {
            return null;
        }

        if (! Schema::hasColumn($tablaAsignaciones, 'colaborador_codigo')) {
            return null;
        }

        $query = DB::table($tablaAsignaciones.' as av')
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

        if (! $row) {
            return null;
        }

        $fechaAsignacion = null;

        if (! empty($row->fecha_inicio)) {
            $fechaAsignacion = Carbon::parse($row->fecha_inicio)->format('d/m/Y');
        } elseif (! empty($row->fecha_asignacion)) {
            $fechaAsignacion = Carbon::parse($row->fecha_asignacion)->format('d/m/Y');
        } elseif (! empty($row->created_at)) {
            $fechaAsignacion = Carbon::parse($row->created_at)->format('d/m/Y');
        }

        return [
            'id' => $row->id ?? null,
            'vin' => $row->vehiculo_vin ?? $row->vehiculo_vin_real ?? null,
            'marca' => $row->vehiculo_marca ?? '—',
            'modelo' => $row->vehiculo_modelo ?? '—',
            'placa' => $row->vehiculo_placa ?? '—',
            'fecha_asignacion' => $fechaAsignacion ?? '—',
            'estado' => $row->estado ?? (! empty($row->activa) ? 'Activa' : '—'),
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

        if (! $tablaProductosVehiculo) {
            return collect();
        }

        if (! Schema::hasColumn($tablaProductosVehiculo, 'vehiculo_vin')) {
            return collect();
        }

        $query = DB::table($tablaProductosVehiculo.' as vp')
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

            if (! empty($row->fecha)) {
                $fecha = Carbon::parse($row->fecha)->format('d/m/Y');
            } elseif (! empty($row->created_at)) {
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
                'estado' => $row->estado ?? (! empty($row->activa) ? 'Activo' : '—'),
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

        if (! Schema::hasTable('compra_detalles')) {
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
            ->route($this->colaboradoresRoutePrefix().'.colaboradores.index')
            ->with('success', 'Estado del colaborador actualizado correctamente.');
    }

    private function colaboradoresRoutePrefix(): string
    {
        return auth()->user()->rolOperativo() == 4 ? 'rrhh' : 'admin';
    }
}
