<?php

namespace App\Http\Controllers\Admin;

use App\Exports\InventarioBodegaExport;
use App\Http\Controllers\Controller;
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
    public function index()
    {
        $user = auth()->user();

        $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
        $bodegas = Bodega::query()
            ->when(is_array($visibleBodegaIds), fn ($query) => $visibleBodegaIds === []
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('id', $visibleBodegaIds))
            ->orderByRaw("CASE WHEN tipo = 'Principal' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get();

        // Totales por bodega (items y stock)
        $resumen = DB::table('inventarios')
            ->select(
                'bodega_id',
                DB::raw('COUNT(*) as items_count'),
                DB::raw('SUM(cantidad) as stock_total')
            )
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
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ((int) auth()->user()->role_id !== 1) {
            abort(403);
        }

        return view('admin.bodegas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ((int) auth()->user()->role_id !== 1) {
            abort(403);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'tipo' => ['required', 'in:Principal,Regional'],
        ]);

        Bodega::create($data);

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

        $inventarioBase = $this->inventarioQuery($request, $bodega);

        $productosTotal = (clone $inventarioBase)->get()->count();

        $stockTotal = (int) ((clone $inventarioBase)->get()->sum('cantidad') ?? 0);

        $costoTotalInventario = (float) (clone $inventarioBase)->get()->sum('costo_total');

        $inventarios = $inventarioBase
            ->orderByDesc('cantidad')
            ->paginate(15)
            ->appends($request->query());

        return view('admin.bodegas.show', [
            'bodega' => $bodega,
            'inventarios' => $inventarios,
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
        $inventarios = $this->inventarioQuery($request, $bodega)
            ->orderBy('p.nombre')
            ->get();

        $filename = 'inventario_bodega_'.$bodega->id.'_'.now()->format('Y-m-d_Hi').'.xlsx';
        $filters = ['Bodega' => $bodega->nombre];

        if ($request->filled('q')) {
            $filters['Búsqueda'] = trim((string) $request->query('q'));
        }

        return Excel::download(
            new InventarioBodegaExport($inventarios, $bodega->nombre, $request->user()->name, $filters),
            $filename
        );
    }

    private function inventarioQuery(Request $request, Bodega $bodega)
    {
        $ultFechaSub = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->select('cd.producto_codigo', DB::raw('MAX(c.fecha_compra) as max_fecha'))
            ->groupBy('cd.producto_codigo');

        $ultCosto = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->joinSub($ultFechaSub, 'uf', function ($join) {
                $join->on('uf.producto_codigo', '=', 'cd.producto_codigo')
                    ->on('uf.max_fecha', '=', 'c.fecha_compra');
            })
            ->select('cd.producto_codigo', DB::raw('cd.precio_unitario as costo_unitario'));

        return DB::table('inventarios as i')
            ->join('productos as p', 'p.codigo', '=', 'i.producto_codigo')
            ->leftJoinSub($ultCosto, 'uc', function ($join) {
                $join->on('uc.producto_codigo', '=', 'i.producto_codigo');
            })
            ->where('i.bodega_id', $bodega->id)
            ->select(
                'i.producto_codigo', 'p.nombre', 'p.descripcion', 'p.categoria', 'p.vida_util_meses',
                DB::raw("SUM(CASE WHEN i.stock_tipo = 'nuevo' THEN i.cantidad ELSE 0 END) as nuevos_disponibles"),
                DB::raw("SUM(CASE WHEN i.stock_tipo = 'usado' THEN i.cantidad ELSE 0 END) as usados_disponibles"),
                DB::raw("SUM(CASE WHEN i.stock_tipo = 'danado' THEN i.cantidad ELSE 0 END) as danados"),
                DB::raw("SUM(CASE WHEN i.stock_tipo = 'perdido' THEN i.cantidad ELSE 0 END) as perdidos"),
                DB::raw("SUM(CASE WHEN i.stock_tipo = 'baja' THEN i.cantidad ELSE 0 END) as bajas"),
                DB::raw('SUM(i.cantidad) as cantidad'),
                DB::raw("MIN(CASE WHEN i.stock_tipo = 'usado' THEN i.vida_util_restante_meses END) as vida_util_restante_meses"),
                DB::raw('MAX(i.updated_at) as updated_at'),
                DB::raw('COALESCE(uc.costo_unitario, 0) as costo_unitario'),
                DB::raw('(SUM(i.cantidad) * COALESCE(uc.costo_unitario, 0)) as costo_total')
            )
            ->groupBy('i.producto_codigo', 'p.nombre', 'p.descripcion', 'p.categoria', 'p.vida_util_meses', 'uc.costo_unitario')
            ->when($request->filled('stock_tipo'), fn ($query) => $query->where('i.stock_tipo', $request->query('stock_tipo')))
            ->when($request->query('q'), function ($query, $q) {
                $query->where(function ($where) use ($q) {
                    $where->where('i.producto_codigo', 'like', "%{$q}%")
                        ->orWhere('p.nombre', 'like', "%{$q}%")
                        ->orWhere('p.descripcion', 'like', "%{$q}%");
                });
            });
    }

    public function edit(string $id)
    {
        if ((int) auth()->user()->role_id !== 1) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        return view('admin.bodegas.edit', compact('bodega'));
    }

    public function update(Request $request, string $id)
    {
        if ((int) auth()->user()->role_id !== 1) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ubicacion' => ['nullable', 'string', 'max:150'],
            'tipo' => ['required', 'in:Principal,Regional'],
        ]);

        $bodega->update($data);

        return redirect()->route('admin.bodegas.index')
            ->with('success', 'Bodega actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        if ((int) auth()->user()->role_id !== 1) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        $tieneInventario = DB::table('inventarios')
            ->where('bodega_id', $bodega->id)
            ->exists();

        if ($tieneInventario) {
            return back()->with('error', 'No puedes eliminar una bodega con inventario.');
        }

        $bodega->delete();

        return redirect()->route('admin.bodegas.index')
            ->with('success', 'Bodega eliminada correctamente.');
    }
}
