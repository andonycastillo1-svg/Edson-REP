<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Services\BodegaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BodegaController extends Controller
{
    public function __construct(private BodegaAccessService $bodegaAccess)
    {
    }

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
    public function show(string $id)
    {
        if (!$this->bodegaAccess->canView(auth()->user(), (int) $id)) {
            abort(403);
        }

        $bodega = Bodega::findOrFail($id);

        $ultFechaSub = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->select(
                'cd.producto_codigo',
                DB::raw('MAX(c.fecha_compra) as max_fecha')
            )
            ->groupBy('cd.producto_codigo');

        $ultCosto = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->joinSub($ultFechaSub, 'uf', function ($join) {
                $join->on('uf.producto_codigo', '=', 'cd.producto_codigo')
                    ->on('uf.max_fecha', '=', 'c.fecha_compra');
            })
            ->select(
                'cd.producto_codigo',
                DB::raw('cd.precio_unitario as costo_unitario')
            );

        $inventarioBase = DB::table('inventarios as i')
            ->join('productos as p', 'p.codigo', '=', 'i.producto_codigo')
            ->leftJoinSub($ultCosto, 'uc', function ($join) {
                $join->on('uc.producto_codigo', '=', 'i.producto_codigo');
            })
            ->where('i.bodega_id', $bodega->id);

        $productosTotal = (clone $inventarioBase)->count();

        $stockTotal = (int) ((clone $inventarioBase)->sum('i.cantidad') ?? 0);

        $costoTotalInventario = (float) ((clone $inventarioBase)
            ->select(DB::raw('SUM(i.cantidad * COALESCE(uc.costo_unitario, 0)) as total'))
            ->value('total') ?? 0);

        $inventarios = $inventarioBase
            ->select(
                'i.id',
                'i.producto_codigo',
                'i.cantidad',
                'p.nombre',
                'p.descripcion',
                'p.unidad_medida',
                'p.vida_util_meses',
                DB::raw('COALESCE(uc.costo_unitario, 0) as costo_unitario'),
                DB::raw('(i.cantidad * COALESCE(uc.costo_unitario, 0)) as costo_total')
            )
            ->orderByDesc('i.cantidad')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bodegas.show', [
            'bodega' => $bodega,
            'inventarios' => $inventarios,
            'productosTotal' => $productosTotal,
            'stockTotal' => $stockTotal,
            'costoTotalInventario' => $costoTotalInventario,
        ]);
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