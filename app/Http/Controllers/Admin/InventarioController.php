<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Services\BodegaAccessService;
use App\Services\InventarioStockService;
use App\Services\NotificacionService;
use App\Services\InventarioLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stockService,
        private InventarioLifecycleService $lifecycleService
    ) {
    }

    public function createEntrada(Bodega $bodega)
    {
        if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $bodega->id)) {
            abort(403);
        }

        $productos = DB::table('productos')
            ->orderBy('nombre')
            ->get();

        return view('admin.inventario.entrada', compact('bodega', 'productos'));
    }

    public function storeEntrada(Request $request, Bodega $bodega)
    {
        if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $bodega->id)) {
            abort(403);
        }

        $data = $request->validate([
            'producto_codigo' => ['required', 'exists:productos,codigo'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data, $bodega) {

            // 1) Crear movimiento "Entrada"
            DB::table('movimientos')->insert([
                'producto_codigo'   => $data['producto_codigo'],
                'bodega_origen_id'  => null,
                'bodega_destino_id' => $bodega->id,
                'tipo_movimiento'   => 'Entrada',
                'cantidad'          => $data['cantidad'],
                'fecha'             => now(),
                'user_id'           => Auth::id(),
                'vehiculo_vin'      => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $this->stockService->incrementar((int) $bodega->id, $data['producto_codigo'], (int) $data['cantidad']);
            $this->lifecycleService->crearNuevas($data['producto_codigo'], (int) $bodega->id, (int) $data['cantidad']);
        });

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarMovimientoInventario(
                $bodega,
                $request->user(),
                'Se registró una entrada manual de ' . $data['cantidad'] . ' unidad(es) en ' . $bodega->nombre . '.'
            )
        );

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.bodegas.show', $bodega->id)
            ->with('success', 'Entrada registrada correctamente.');
    }
}
