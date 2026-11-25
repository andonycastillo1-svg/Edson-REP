<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Services\DelegacionesOperativas;
use App\Services\ExportarExcel;
use App\Sistema\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioAutorizadoController extends Controller
{
    public function __construct(private Permisos $permisos, private DelegacionesOperativas $delegaciones) {}

    public function index(Request $request, ExportarExcel $excel)
    {
        $usuario = $request->user();
        $permiso = $request->boolean('xlsx') ? 'bodegas.exportar' : 'bodegas.consultar';
        $permanente = $this->permisos->permite($usuario, $permiso) && $this->permisos->permite($usuario, 'bodegas.consultar');
        $delegadas = $this->delegaciones->bodegas($usuario, $permiso);
        abort_unless($permanente || $delegadas !== [], 403);

        $propias = $permanente ? $this->delegaciones->bodegasTitular($usuario) : [];
        $ids = array_unique([...$delegadas, ...$propias]);
        $datos = $request->validate(['bodega' => ['nullable', 'integer']]);
        $bodega = $datos['bodega'] ?? null;
        abort_if($bodega && !in_array((int) $bodega, $ids, true), 403);
        $consulta = Inventario::with('bodega', 'producto')->whereIn('bodega_id', $ids)
            ->when($bodega, fn ($q) => $q->where('bodega_id', $bodega))->orderBy('id');

        foreach ($this->delegaciones->vigentes($usuario, $permiso) as $fila) {
            $solicitadas = $bodega ? [(int) $bodega] : $delegadas;
            $aplicables = array_intersect($this->delegaciones->bodegasTitular($fila->titular), $fila->bodegas->modelKeys(),
                array_diff($solicitadas, $propias), $delegadas);
            if ($aplicables !== []) {
                $this->delegaciones->registrar($fila, $permiso, $bodega);
            }
        }

        if ($request->boolean('xlsx')) {
            return $excel->descargar('existencias_autorizadas', ['Bodega', 'Código', 'Producto', 'Condición', 'Cantidad'],
                $consulta->lazy(200)->map(fn ($fila) => [$fila->bodega->nombre, $fila->producto_codigo, $fila->producto?->nombre, $fila->stock_tipo, $fila->cantidad]));
        }

        $permisoMovimientos = $this->permisos->permite($usuario, 'inventario.movimientos');
        $movimientos = $permisoMovimientos && $bodega && $permanente && in_array((int) $bodega, $propias, true)
            ? DB::table('movimientos')->where(fn ($q) => $q->where('bodega_origen_id', $bodega)->orWhere('bodega_destino_id', $bodega))
                ->latest('fecha')->orderByDesc('id')->paginate(20, pageName: 'movimientos')->withQueryString()
            : null;

        return response()->view('flujo.inventario-autorizado', [
            'existencias' => $consulta->paginate(30)->withQueryString(),
            'bodegas' => Bodega::whereKey($ids)->orderBy('nombre')->get(),
            'exportar' => ($permanente && $this->permisos->permite($usuario, 'bodegas.exportar'))
                || $this->delegaciones->bodegas($usuario, 'bodegas.exportar') !== [],
            'movimientos' => $movimientos,
        ])->header('Cache-Control', 'private, no-store');
    }
}
