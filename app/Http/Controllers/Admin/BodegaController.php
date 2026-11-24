<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Presentacion;
use App\Exports\InventarioBodegaExport;
use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Sistema\ConsultaCampos;
use App\Sistema\Permisos;
use App\Models\Bodega;
use App\Services\BodegaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class BodegaController extends Controller
{
    public function __construct(private BodegaAccessService $bodegaAccess) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
        $consulta = Bodega::query()
            ->when(is_array($visibleBodegaIds), fn ($query) => $visibleBodegaIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $visibleBodegaIds));
        app(ConsultaCampos::class)->filtrar($consulta, $request, 'bodegas');
        if ($request->boolean('xlsx')) {
            $exportar = app(ExportacionesOperativas::class);
            $exportar->autorizar($request, 'bodegas');

            return $exportar->existencias($consulta);
        }
        $bodegas = $consulta->orderByRaw("CASE WHEN tipo = 'Principal' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get();

        // Totales por bodega (items y stock)
        $resumen = DB::table('inventarios')
            ->select(
                'bodega_id',
                DB::raw('COUNT(DISTINCT producto_codigo) as items_count'),
                DB::raw('SUM(cantidad) as stock_total')
            )
            ->whereIn('stock_tipo', ['nuevo', 'usado'])
            ->groupBy('bodega_id')
            ->get()
            ->keyBy('bodega_id');

        // Último movimiento por bodega
        $ultimos = DB::table('movimientos')
            ->select(
                DB::raw('COALESCE(bodega_origen_id, bodega_destino_id) as bodega_id'),
                DB::raw('MAX(fecha) as ultima_fecha')
            )
            ->groupBy(DB::raw('COALESCE(bodega_origen_id, bodega_destino_id)'))
            ->get()
            ->keyBy('bodega_id');

        // Adjuntamos a cada bodega
        $bodegas->transform(function ($b) use ($resumen, $ultimos) {
            $b->items_count = (int) ($resumen[$b->id]->items_count ?? 0);
            $b->stock_total = (int) ($resumen[$b->id]->stock_total ?? 0);
            $b->ultima_fecha = $ultimos[$b->id]->ultima_fecha ?? null;

            return $b;
        });

        return view('admin.bodegas.index', [
            'bodegas' => $bodegas,
            'tablaCampos' => app(Presentacion::class)->operativa('bodegas', $user, $bodegas->pluck('id')),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (auth()->user()->rolOperativo() !== 1) {
            abort(403);
        }

        return view('admin.bodegas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth()->user()->rolOperativo() !== 1) {
            abort(403);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'tipo' => ['required', 'in:Principal,Regional'],
        ]);

        app(Presentacion::class)->validar($request, 'bodegas', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'bodegas', true);
        DB::transaction(function () use ($data, $campos, $valores) {
            $bodega = Bodega::create($data);
            $campos->guardar('bodegas', $bodega->id, $valores);
        });

        return redirect()->route('admin.bodegas.index')
            ->with('success', 'Bodega creada correctamente.');
    }

    /**
     * Display the specified resource.
     * Aquí lo usamos como "Inventario de la bodega"
     */
    public function show(Request $request, string $id)
    {
        if (! $this->bodegaAccess->canView(auth()->user(), (int) $id)) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        $ultimoCosto = $this->ultimoCosto();
        $inventarioBase = $this->inventarioQuery($request, $bodega);

        $productosTotal = (clone $inventarioBase)->count();

        $stockTotal = (int) ((clone $inventarioBase)->sum('i.cantidad') ?? 0);

        $costoTotalInventario = (float) ((clone $inventarioBase)
            ->selectRaw('SUM(i.cantidad * COALESCE((' . $ultimoCosto->toSql() . '), 0)) as total', $ultimoCosto->getBindings())
            ->value('total') ?? 0);

        $inventarios = $inventarioBase
            ->select(
                'i.producto_codigo',
                'i.cantidad',
                'p.nombre',
                'p.descripcion',
                'p.categoria',
                'p.vida_util_meses',
            )
            ->selectRaw('COALESCE((' . $ultimoCosto->toSql() . '), 0) as costo_unitario', $ultimoCosto->getBindings())
            ->selectRaw('i.cantidad * COALESCE((' . $ultimoCosto->toSql() . '), 0) as costo_total', $ultimoCosto->getBindings())
            ->orderByDesc('i.cantidad')
            ->paginate(15)
            ->appends($request->query());

        $tablaCampos = $this->camposInventario($request, $bodega, $inventarios->pluck('producto_codigo'));

        return view('admin.bodegas.show', [
            'bodega' => $bodega,
            'inventarios' => $inventarios,
            'tablaCampos' => $tablaCampos,
            'productosTotal' => $productosTotal,
            'stockTotal' => $stockTotal,
            'costoTotalInventario' => $costoTotalInventario,
        ]);
    }

    public function exportInventario(Request $request, string $id)
    {
        if (! $this->bodegaAccess->canView(auth()->user(), (int) $id)) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);
        $ultimoCosto = $this->ultimoCosto();
        $inventarios = $this->inventarioQuery($request, $bodega)
            ->select(
                'i.producto_codigo',
                'i.cantidad',
                'i.updated_at',
                'p.nombre',
                'p.descripcion',
                'p.categoria',
                'p.vida_util_meses',
            )
            ->selectRaw('COALESCE((' . $ultimoCosto->toSql() . '), 0) as costo_unitario', $ultimoCosto->getBindings())
            ->selectRaw('i.cantidad * COALESCE((' . $ultimoCosto->toSql() . '), 0) as costo_total', $ultimoCosto->getBindings())
            ->orderBy('p.nombre')
            ->get();

        $filename = 'inventario_bodega_'.$bodega->id.'_'.now()->format('Y-m-d_Hi').'.xlsx';
        $filters = ['Bodega' => $bodega->nombre, 'Condición' => ($request->query('stock_tipo') ?: 'Disponible')];

        if ($request->filled('q')) {
            $filters['Búsqueda'] = trim((string) $request->query('q'));
        }

        $campos = app(Permisos::class)->permite($request->user(), 'inventario.datos_exportar')
            ? $this->camposInventario($request, $bodega, $inventarios->pluck('producto_codigo'), 'exportacion')
            : [];
        return Excel::download(
            new InventarioBodegaExport($inventarios, $bodega->nombre, $request->user()->name, $filters, $campos),
            $filename
        );
    }

    private function ultimoCosto()
    {
        return DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->whereColumn('cd.producto_codigo', 'i.producto_codigo')
            ->orderByDesc('c.fecha_compra')->orderByDesc('cd.id')
            ->limit(1)->select('cd.precio_unitario');
    }

    private function inventarioQuery(Request $request, Bodega $bodega)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'stock_tipo' => ['nullable', 'in:nuevo,usado,danado,perdido,baja'],
        ]);
        $resumen = $this->inventariosDeBodega($request, $bodega)
            ->select('producto_codigo')
            ->selectRaw('SUM(cantidad) AS cantidad, MAX(updated_at) AS updated_at')
            ->groupBy('producto_codigo');

        return DB::query()->fromSub($resumen, 'i')
            ->join('productos as p', 'p.codigo', '=', 'i.producto_codigo')
            ->when($filtros['q'] ?? null, function ($query, $q) {
                $query->where(function ($where) use ($q) {
                    $where->where('i.producto_codigo', 'like', "%{$q}%")
                        ->orWhere('p.nombre', 'like', "%{$q}%")
                        ->orWhere('p.descripcion', 'like', "%{$q}%");
                });
            });
    }

    private function inventariosDeBodega(Request $request, Bodega $bodega)
    {
        $tipos = $request->filled('stock_tipo') ? [$request->query('stock_tipo')] : ['nuevo', 'usado'];

        $consulta = DB::table('inventarios')->where('bodega_id', $bodega->id)->whereIn('stock_tipo', $tipos);
        app(ConsultaCampos::class)->filtrar($consulta, $request, 'inventario');

        return $consulta;
    }

    private function camposInventario(Request $request, Bodega $bodega, iterable $codigos, string $superficie = 'tabla'): array
    {
        $presentacion = app(Presentacion::class);
        $campos = $presentacion->personalizadas('inventario', $request->user(), $superficie);
        $filas = $campos->isEmpty() ? collect() : $this->inventariosDeBodega($request, $bodega)
            ->whereIn('producto_codigo', $codigos)->orderBy('stock_tipo')->get(['id', 'producto_codigo', 'stock_tipo']);
        $lector = app(ValoresCampos::class);
        $valores = $lector->cargar($campos, $filas->pluck('id'));
        $valoresAgrupados = $filas->groupBy('producto_codigo')->map(fn ($grupo) => $campos->mapWithKeys(
            fn ($campo) => [$campo->id => $grupo->map(function ($fila) use ($lector, $campo, $valores, $grupo) {
                $valor = $lector->mostrar($campo, $valores->get($fila->id)?->get($campo->id));

                return $valor === '' || $grupo->count() === 1 ? $valor : ucfirst($fila->stock_tipo).': '.$valor;
            })->filter(fn ($valor) => $valor !== '')->implode(' · ')]
        ));

        return [
            'campos' => $campos,
            'valoresPresentados' => $valoresAgrupados,
            'filtros' => $presentacion->personalizadas('inventario', $request->user(), 'filtro'),
        ];
    }

    public function edit(string $id)
    {
        if (auth()->user()->rolOperativo() !== 1) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        return view('admin.bodegas.edit', compact('bodega'));
    }

    public function update(Request $request, string $id)
    {
        if (auth()->user()->rolOperativo() !== 1) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'tipo' => ['required', 'in:Principal,Regional'],
        ]);

        app(Presentacion::class)->validar($request, 'bodegas', false);
        $campos = app(ValoresCampos::class);
        DB::transaction(function () use ($request, $bodega, $data, $campos) {
            app(\App\Sistema\Seguridad::class)->bloquear();
            $ajustes = app(\App\Sistema\Ajustes::class);
            $ajustes->refrescar('general');
            $bodega = Bodega::whereKey($bodega->id)->lockForUpdate()->firstOrFail();
            if ((int) $this->bodegaAccess->principal()?->id === (int) $bodega->id && $data['tipo'] !== 'Principal'
                && ($ajustes->general('bodega_principal') !== null || $this->bodegaAccess->tieneHistorialOperativo())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['tipo' => 'La bodega principal debe conservar su tipo.']);
            }
            $valores = $campos->preparar($request, 'bodegas', false, (string) $bodega->id);
            $bodega->update($data);
            $campos->guardar('bodegas', $bodega->id, $valores);
        });

        return redirect()->route('admin.bodegas.index')
            ->with('success', 'Bodega actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        if (auth()->user()->rolOperativo() !== 1) {
            abort(403);
        }

        $eliminada = DB::transaction(function () use ($id) {
            app(\App\Sistema\Seguridad::class)->bloquear();
            $ajustes = app(\App\Sistema\Ajustes::class);
            $ajustes->refrescar('general');
            $bodega = Bodega::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if((int) $this->bodegaAccess->principal()?->id === (int) $bodega->id
                && ($ajustes->general('bodega_principal') !== null || $this->bodegaAccess->tieneHistorialOperativo()), 422);

            if (DB::table('inventarios')->where('bodega_id', $bodega->id)->exists()) {
                return false;
            }

            $bodega->delete();

            return true;
        });
        if (!$eliminada) {
            return back()->with('error', 'No puedes eliminar una bodega con inventario.');
        }

        return redirect()->route('admin.bodegas.index')
            ->with('success', 'Bodega eliminada correctamente.');
    }
}
