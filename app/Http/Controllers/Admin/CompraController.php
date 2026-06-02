<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\BodegaAccessService;
use App\Services\InventarioStockService;

class CompraController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stockService
    ) {
    }

    public function index()
    {
        $compras = DB::table('compras as c')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->select('c.*', 'p.nombre as proveedor_nombre')
            ->orderByDesc('c.id')
            ->get();

        $proveedores = DB::table('proveedores')->orderBy('nombre')->get();

        $productos = DB::table('productos')
            ->orderBy('nombre')
            ->get();

        $categorias = DB::table('productos')
            ->whereNotNull('categoria')
            ->where('categoria', '<>', '')
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->filter()
            ->values();

        return view('admin.compras.index', compact(
            'compras',
            'proveedores',
            'productos',
            'categorias'
        ));
    }

    public function create()
    {
        $proveedores = DB::table('proveedores')->orderBy('nombre')->get();

        $productos = DB::table('productos')
            ->orderBy('nombre')
            ->get();

        $categorias = DB::table('productos')
            ->whereNotNull('categoria')
            ->where('categoria', '<>', '')
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->filter()
            ->values();

        return view('admin.compras.create', compact(
            'proveedores',
            'productos',
            'categorias'
        ));
    }

    public function store(Request $request)
    {
        $hoy = Carbon::today();
        $min = $hoy->copy()->subDays(7);
        $max = $hoy->copy()->addDays(7);

        $fc = $request->input('fecha_compra');

        if ($fc) {
            try {
                $fechaCompra = Carbon::parse($fc)->startOfDay();

                if ($fechaCompra->lt($min) || $fechaCompra->gt($max)) {
                    $msg = 'No puedes registrar compras fuera del rango permitido. '
                        . 'Solo se permite desde ' . $min->format('d/m/Y')
                        . ' hasta ' . $max->format('d/m/Y') . '.';

                    return back()
                        ->withErrors(['fecha_compra' => $msg])
                        ->with('error', $msg)
                        ->with('openCompraModal', true)
                        ->withInput();
                }
            } catch (\Exception $e) {
                $msg = 'La fecha de compra no tiene un formato válido.';

                return back()
                    ->withErrors(['fecha_compra' => $msg])
                    ->with('error', $msg)
                    ->with('openCompraModal', true)
                    ->withInput();
            }
        }

        $data = $request->validate([
            'fecha_compra' => ['required', 'date'],
            'no_factura' => ['required', 'string', 'max:50'],

            'proveedor_tipo' => ['required', 'in:existente,nuevo'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'proveedor_nombre' => ['nullable', 'string', 'max:255'],

            'forma_pago' => ['required', 'in:Al contado,Crédito 30 días,Crédito 60 días,Crédito 90 días'],
            'proyecto' => ['nullable', 'string', 'max:150'],
            'solicitado_por' => ['nullable', 'string', 'max:150'],
            'autorizado_por' => ['nullable', 'string', 'max:150'],
            'a_utilizarse' => ['nullable', 'string', 'max:255'],

            'producto_tipo' => ['required', 'array', 'min:1'],
            'producto_tipo.*' => ['required', 'in:existente,nuevo'],

            'producto_codigo' => ['required', 'array', 'min:1'],
            'producto_codigo.*' => ['nullable', 'string', 'max:50'],

            'producto_nombre' => ['nullable', 'array'],
            'producto_nombre.*' => ['nullable', 'string', 'max:150'],

            'producto_codigo_nuevo' => ['nullable', 'array'],
            'producto_codigo_nuevo.*' => ['nullable', 'string', 'max:50'],

            'producto_unidad' => ['nullable', 'array'],
            'producto_unidad.*' => ['nullable', 'string', 'max:50'],

            'producto_categoria' => ['nullable', 'array'],
            'producto_categoria.*' => ['nullable', 'string', 'max:50'],

            'producto_vida_util_meses' => ['nullable', 'array'],
            'producto_vida_util_meses.*' => ['nullable', 'integer', 'min:1', 'max:1200'],

            'producto_descripcion' => ['nullable', 'array'],
            'producto_descripcion.*' => ['nullable', 'string'],

            'cantidad' => ['required', 'array', 'min:1'],
            'cantidad.*' => ['required', 'integer', 'min:1'],

            'precio_unitario' => ['required', 'array', 'min:1'],
            'precio_unitario.*' => ['required', 'numeric', 'min:0'],

            'pdfs' => ['nullable', 'array'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        $bodegaPrincipalId = DB::table('bodegas')
            ->where('tipo', 'Principal')
            ->orderBy('id')
            ->value('id');

        if (!$bodegaPrincipalId) {
            return back()
                ->with('error', 'No existe una bodega Principal creada.')
                ->withInput();
        }

        $proveedorId = null;

        if ($data['proveedor_tipo'] === 'nuevo') {
            $nombreNuevo = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($data['proveedor_nombre'] ?? ''))));

            if ($nombreNuevo === '') {
                return back()
                    ->with('error', 'Debe escribir el nombre del proveedor nuevo.')
                    ->withInput();
            }

            $prov = DB::table('proveedores')
                ->whereRaw('UPPER(nombre) = ?', [$nombreNuevo])
                ->first();

            if ($prov) {
                $proveedorId = $prov->id;
            } else {
                $proveedorId = DB::table('proveedores')->insertGetId([
                    'nombre' => $nombreNuevo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            if (empty($data['proveedor_id'])) {
                return back()
                    ->with('error', 'Debe seleccionar un proveedor existente.')
                    ->withInput();
            }

            $proveedorId = $data['proveedor_id'];
        }

        DB::transaction(function () use ($request, $data, $bodegaPrincipalId, $proveedorId) {
            $compraId = DB::table('compras')->insertGetId([
                'fecha_compra' => $data['fecha_compra'],
                'no_factura' => $data['no_factura'],
                'proveedor_id' => $proveedorId,
                'forma_pago' => $data['forma_pago'],
                'proyecto' => $data['proyecto'] ?? null,
                'solicitado_por' => $data['solicitado_por'] ?? null,
                'autorizado_por' => $data['autorizado_por'] ?? null,
                'a_utilizarse' => $data['a_utilizarse'] ?? null,
                'total_factura' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalFactura = 0;

            $tipos = $data['producto_tipo'];
            $codigos = $data['producto_codigo'];
            $cant = $data['cantidad'];
            $precios = $data['precio_unitario'];

            $nombres = $data['producto_nombre'] ?? [];
            $codNuevos = $data['producto_codigo_nuevo'] ?? [];
            $unidades = $data['producto_unidad'] ?? [];
            $categorias = $data['producto_categoria'] ?? [];
            $vidasUtiles = $data['producto_vida_util_meses'] ?? [];
            $descs = $data['producto_descripcion'] ?? [];

            for ($i = 0; $i < count($tipos); $i++) {
                $codigoFinal = null;

                if (($tipos[$i] ?? 'existente') === 'nuevo') {
                    $nombre = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($nombres[$i] ?? ''))));
                    $codigoNuevo = strtoupper(preg_replace('/\s+/', '', trim((string) ($codNuevos[$i] ?? ''))));
                    $unidad = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($unidades[$i] ?? 'UND'))));
                    $categoria = preg_replace('/\s+/', ' ', trim((string) ($categorias[$i] ?? '')));
                    $vidaUtilMeses = !empty($vidasUtiles[$i]) ? (int) $vidasUtiles[$i] : null;
                    $descripcion = trim((string) ($descs[$i] ?? ''));

                    if ($nombre === '' || $codigoNuevo === '') {
                        throw new \Exception('Hay una línea con PRODUCTO NUEVO incompleto (nombre o código vacío).');
                    }

                    if ($categoria === '') {
                        throw new \Exception('Hay una línea con PRODUCTO NUEVO sin categoría.');
                    }

                    $prod = DB::table('productos')
                        ->where('codigo', $codigoNuevo)
                        ->first();

                    if (!$prod) {
                        DB::table('productos')->insert([
                            'codigo' => $codigoNuevo,
                            'nombre' => $nombre,
                            'descripcion' => $descripcion !== '' ? $descripcion : null,
                            'unidad_medida' => $unidad,
                            'categoria' => $categoria,
                            'vida_util_meses' => $vidaUtilMeses,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('productos')
                            ->where('codigo', $codigoNuevo)
                            ->update([
                                'categoria' => $categoria !== '' ? $categoria : $prod->categoria,
                                'vida_util_meses' => $vidaUtilMeses ?? $prod->vida_util_meses,
                                'updated_at' => now(),
                            ]);
                    }

                    $codigoFinal = $codigoNuevo;
                } else {
                    $codigoFinal = $codigos[$i] ?? null;

                    if (!$codigoFinal || !DB::table('productos')->where('codigo', $codigoFinal)->exists()) {
                        throw new \Exception('Hay una línea con PRODUCTO EXISTENTE no seleccionado o inválido.');
                    }
                }

                $valorTotal = (float) $cant[$i] * (float) $precios[$i];
                $totalFactura += $valorTotal;

                DB::table('compra_detalles')->insert([
                    'compra_id' => $compraId,
                    'producto_codigo' => $codigoFinal,
                    'cantidad' => (int) $cant[$i],
                    'precio_unitario' => (float) $precios[$i],
                    'valor_total' => $valorTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('movimientos')->insert([
                    'producto_codigo' => $codigoFinal,
                    'bodega_origen_id' => null,
                    'bodega_destino_id' => $bodegaPrincipalId,
                    'tipo_movimiento' => 'Entrada',
                    'cantidad' => (int) $cant[$i],
                    'fecha' => now(),
                    'user_id' => Auth::id(),
                    'vehiculo_vin' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->stockService->incrementar((int) $bodegaPrincipalId, $codigoFinal, (int) $cant[$i]);
            }

            DB::table('compras')->where('id', $compraId)->update([
                'total_factura' => $totalFactura,
                'updated_at' => now(),
            ]);

            if ($request->hasFile('pdfs')) {
                foreach ($request->file('pdfs') as $file) {
                    $path = $file->store("compras/{$compraId}", 'public');

                    DB::table('compra_archivos')->insert([
                        'compra_id' => $compraId,
                        'ruta' => $path,
                        'nombre_original' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'tamano' => $file->getSize(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()->route($routePrefix . '.compras.index')
            ->with('success', 'Compra registrada y entrada aplicada a inventario (Bodega Principal).');
    }

    public function show(string $id)
    {
        $compra = DB::table('compras as c')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->select('c.*', 'p.nombre as proveedor_nombre')
            ->where('c.id', $id)
            ->first();

        if (!$compra) {
            abort(404);
        }

        $detalles = DB::table('compra_detalles as d')
            ->join('productos as pr', 'pr.codigo', '=', 'd.producto_codigo')
            ->where('d.compra_id', $id)
            ->select('d.*', 'pr.nombre as producto_nombre', 'pr.categoria')
            ->orderBy('d.id')
            ->get();

        $archivos = DB::table('compra_archivos')
            ->where('compra_id', $id)
            ->orderBy('id')
            ->get()
            ->map(function ($a) {
                $a->url = Storage::disk('public')->url($a->ruta);
                return $a;
            });

        return view('admin.compras.show', compact('compra', 'detalles', 'archivos'));
    }
}