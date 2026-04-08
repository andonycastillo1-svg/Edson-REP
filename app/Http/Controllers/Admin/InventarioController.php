<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function createEntrada(Bodega $bodega)
    {
        $productos = DB::table('productos')
            ->orderBy('nombre')
            ->get();

        return view('admin.inventario.entrada', compact('bodega', 'productos'));
    }

    public function storeEntrada(Request $request, Bodega $bodega)
    {
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

            // 2) Sumar a inventarios (o crear si no existe)
            $inv = DB::table('inventarios')
                ->where('bodega_id', $bodega->id)
                ->where('producto_codigo', $data['producto_codigo'])
                ->first();

            if ($inv) {
                DB::table('inventarios')
                    ->where('id', $inv->id)
                    ->update([
                        'cantidad'   => $inv->cantidad + $data['cantidad'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('inventarios')->insert([
                    'bodega_id'       => $bodega->id,
                    'producto_codigo' => $data['producto_codigo'],
                    'cantidad'        => $data['cantidad'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        });

        return redirect()
            ->route('admin.bodegas.show', $bodega->id)
            ->with('success', 'Entrada registrada correctamente.');
    }
}
