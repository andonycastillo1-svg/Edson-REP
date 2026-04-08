<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrasladoController extends Controller
{
    public function index(Request $request)
    {
        $producto = $request->get('producto'); // ahora es select (codigo)
        $origen   = $request->get('origen');
        $destino  = $request->get('destino');

        $traslados = Movimiento::with(['bodegaOrigen', 'bodegaDestino', 'producto'])
            ->where('tipo_movimiento', 'Traslado')
            ->when($producto, fn($query) => $query->where('producto_codigo', $producto))
            ->when($origen, fn($query) => $query->where('bodega_origen_id', $origen))
            ->when($destino, fn($query) => $query->where('bodega_destino_id', $destino))
            ->orderByDesc('fecha')
            ->paginate(20)
            ->withQueryString();

        $bodegas   = Bodega::orderBy('nombre')->get();
        $productos = Producto::orderBy('nombre')->get();

        return view('admin.traslados.index', compact('traslados', 'bodegas', 'productos', 'producto', 'origen', 'destino'));
    }

    public function create(Request $request)
    {
        $bodegas   = Bodega::orderBy('nombre')->get();
        $productos = Producto::orderBy('nombre')->get();
        $origenId  = $request->get('origen');

        return view('admin.traslados.create', compact('bodegas', 'productos', 'origenId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bodega_origen_id'  => ['required', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id' => ['required', 'exists:bodegas,id'],

            // líneas
            'lineas'                 => ['required', 'array', 'min:1'],
            'lineas.*.producto_codigo' => ['required', 'exists:productos,codigo'],
            'lineas.*.cantidad'        => ['required', 'integer', 'min:1'],
        ], [
            'lineas.required' => 'Debes agregar al menos un producto al traslado.'
        ]);

        $origenId  = (int)$data['bodega_origen_id'];
        $destinoId = (int)$data['bodega_destino_id'];

        // Unificamos líneas repetidas (si el usuario eligió el mismo producto 2 veces)
        $agrupadas = [];
        foreach ($data['lineas'] as $l) {
            $cod = $l['producto_codigo'];
            $cant = (int)$l['cantidad'];
            if (!isset($agrupadas[$cod])) $agrupadas[$cod] = 0;
            $agrupadas[$cod] += $cant;
        }

        DB::transaction(function () use ($agrupadas, $origenId, $destinoId) {
            $fecha = now();
            $userId = auth()->id();

            foreach ($agrupadas as $codigo => $cantidad) {

                // ORIGEN: bloquear y validar stock
                $invOrigen = Inventario::where('producto_codigo', $codigo)
                    ->where('bodega_id', $origenId)
                    ->lockForUpdate()
                    ->first();

                $stockOrigen = (int)($invOrigen?->cantidad ?? 0);

                if ($stockOrigen < $cantidad) {
                    throw ValidationException::withMessages([
                        'lineas' => "Stock insuficiente para {$codigo}. Disponible: {$stockOrigen}, solicitado: {$cantidad}."
                    ]);
                }

                // restar
                $invOrigen->cantidad = $stockOrigen - $cantidad;
                $invOrigen->save();

                // DESTINO: bloquear/crear
                $invDestino = Inventario::where('producto_codigo', $codigo)
                    ->where('bodega_id', $destinoId)
                    ->lockForUpdate()
                    ->first();

                if (!$invDestino) {
                    $invDestino = Inventario::create([
                        'producto_codigo' => $codigo,
                        'bodega_id'       => $destinoId,
                        'cantidad'        => 0,
                    ]);
                }

                $invDestino->cantidad = (int)$invDestino->cantidad + $cantidad;
                $invDestino->save();

                // movimiento (1 por línea)
                Movimiento::create([
                    'producto_codigo'   => $codigo,
                    'bodega_origen_id'  => $origenId,
                    'bodega_destino_id' => $destinoId,
                    'tipo_movimiento'   => 'Traslado',
                    'cantidad'          => $cantidad,
                    'fecha'             => $fecha,
                    'user_id'           => $userId,
                ]);
            }
        });

        return redirect()->route('admin.traslados.index')->with('ok', 'Traslado realizado correctamente.');
    }
}