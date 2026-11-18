<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Auditoria;
use App\Sistema\Presentacion;
use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Services\DocumentoPrivado;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BodegaAccessService;
use App\Services\InventarioStockService;
use App\Services\NotificacionService;

class CompraController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stockService
    ) {
    }

    public function index()
    {
        $this->autorizarConsulta();
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
            ->merge(app(\App\Sistema\Ajustes::class)->general('categorias', []))->unique()->sort()->values();

        return view('admin.compras.index', compact(
            'compras',
            'proveedores',
            'productos',
            'categorias'
        ));
    }

    public function create()
    {
        $this->autorizarConsulta();
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
            ->merge(app(\App\Sistema\Ajustes::class)->general('categorias', []))->unique()->sort()->values();

        return view('admin.compras.create', compact(
            'proveedores',
            'productos',
            'categorias'
        ));
    }

    public function store(Request $request)
    {
        $bodega = $this->bodegaAccess->principal() ?? abort(404, 'No hay bodega principal configurada.');
        abort_unless($this->bodegaAccess->canReceiveStock($request->user(), (int) $bodega->id), 403);

        $data = $request->validate([
            'fecha_compra' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . today()->subDays(7)->toDateString(), 'before_or_equal:' . today()->addDays(7)->toDateString()],
            'no_factura' => ['required', 'string', 'max:50'],
            'proveedor_tipo' => ['required', 'in:existente,nuevo'],
            'proveedor_id' => ['required_if:proveedor_tipo,existente', 'nullable', 'exists:proveedores,id'],
            'proveedor_nombre' => ['required_if:proveedor_tipo,nuevo', 'nullable', 'string', 'max:150'],
            'forma_pago' => ['required', 'in:Al contado,Crédito 30 días,Crédito 60 días,Crédito 90 días'],
            'proyecto' => ['nullable', 'string', 'max:150'],
            'solicitado_por' => ['nullable', 'string', 'max:150'],
            'autorizado_por' => ['nullable', 'string', 'max:150'],
            'a_utilizarse' => ['nullable', 'string', 'max:255'],
            'producto_tipo' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'producto_tipo.*' => ['required', 'in:existente,nuevo'],
            'producto_codigo' => ['required', 'array', 'list'],
            'producto_codigo.*' => ['nullable', 'string', 'max:50'],
            'producto_codigo_nuevo' => ['nullable', 'array', 'list'],
            'producto_codigo_nuevo.*' => ['nullable', 'string', 'max:50'],
            'producto_nombre' => ['nullable', 'array', 'list'],
            'producto_nombre.*' => ['nullable', 'string', 'max:150'],
            'producto_unidad' => ['nullable', 'array', 'list'],
            'producto_unidad.*' => ['nullable', 'string', 'max:50'],
            'producto_categoria' => ['nullable', 'array', 'list'],
            'producto_categoria.*' => ['nullable', 'string', 'max:50'],
            'producto_vida_util_meses' => ['nullable', 'array', 'list'],
            'producto_vida_util_meses.*' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'producto_descripcion' => ['nullable', 'array', 'list'],
            'producto_descripcion.*' => ['nullable', 'string', 'max:10000'],
            'cantidad' => ['required', 'array', 'list'],
            'cantidad.*' => ['required', 'integer', 'min:1', 'max:10000'],
            'precio_unitario' => ['required', 'array', 'list'],
            'precio_unitario.*' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'pdfs' => ['nullable', 'array', 'max:10'],
            'pdfs.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        app(Presentacion::class)->validar($request, 'compras', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'compras', true);
        [$lineas, $total] = $this->lineasCompra($data);
        foreach ($lineas as &$linea) {
            if ($linea['nuevo']) {
                $entrada = $request->all('campos_producto')['campos_producto'][$linea['indice']] ?? [];
                $linea['campos'] = $campos->preparar($request, 'productos', true, entrada: $entrada);
            }
        }
        unset($linea);
        $prefijo = $this->rutaPrefijo();
        $bloquearPrincipal = !$this->bodegaAccess->tieneHistorialOperativo();
        $compra = null;
        $adjuntos = [];

        try {
            foreach ($request->file('pdfs', []) as $archivo) {
                $adjuntos[] = [
                    'ruta' => DocumentoPrivado::guardar($archivo, 'compras'),
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime' => $archivo->getMimeType(),
                    'tamano' => $archivo->getSize(),
                ];
            }

            $compra = DB::transaction(function () use ($data, $bodega, $lineas, $total, $adjuntos, $request, $campos, $valores, $bloquearPrincipal) {
                if ($bloquearPrincipal) {
                    app(\App\Sistema\Seguridad::class)->bloquear();
                    app(\App\Sistema\Ajustes::class)->refrescar('general');
                    if ((int) $this->bodegaAccess->principal()?->id !== (int) $bodega->id) {
                        throw ValidationException::withMessages(['bodega_id' => 'La bodega principal cambió. Vuelve a enviar la compra.']);
                    }
                }
                $proveedorId = $this->proveedor($data);
                $compraId = DB::table('compras')->insertGetId([
                    'fecha_compra' => $data['fecha_compra'],
                    'no_factura' => $data['no_factura'],
                    'proveedor_id' => $proveedorId,
                    'forma_pago' => $data['forma_pago'],
                    'proyecto' => $data['proyecto'] ?? null,
                    'solicitado_por' => $data['solicitado_por'] ?? null,
                    'autorizado_por' => $data['autorizado_por'] ?? null,
                    'a_utilizarse' => $data['a_utilizarse'] ?? null,
                    'total_factura' => $this->decimal($total),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($lineas as $linea) {
                    $codigo = $linea['codigo'];
                    if ($linea['nuevo']) {
                        if (DB::table('productos')->where('codigo', $codigo)->exists()) {
                            throw ValidationException::withMessages(['producto_codigo_nuevo' => "El producto {$codigo} ya existe. Selecciónalo del catálogo."]);
                        }

                        DB::table('productos')->insert($linea['nuevo'] + ['codigo' => $codigo, 'created_at' => now(), 'updated_at' => now()]);
                        $campos->guardar('productos', $codigo, $linea['campos']);
                    } elseif (!DB::table('productos')->where('codigo', $codigo)->exists()) {
                        throw ValidationException::withMessages(['producto_codigo' => "El producto {$codigo} no está en el catálogo."]);
                    }

                    DB::table('compra_detalles')->insert([
                        'compra_id' => $compraId,
                        'producto_codigo' => $codigo,
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $this->decimal($linea['precio']),
                        'valor_total' => $this->decimal($linea['cantidad'] * $linea['precio']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->stockService->incrementar((int) $bodega->id, $codigo, $linea['cantidad'], $linea['precio'] / 100);

                    DB::table('movimientos')->insert([
                        'producto_codigo' => $codigo,
                        'bodega_destino_id' => $bodega->id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => $linea['cantidad'],
                        'fecha' => now(),
                        'user_id' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($adjuntos as $adjunto) {
                    DB::table('compra_archivos')->insert($adjunto + [
                        'compra_id' => $compraId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                $campos->guardar('compras', $compraId, $valores);
                app(Auditoria::class)->registrar('compras', 'registrar', $compraId, null, ['bodega_id' => $bodega->id, 'lineas' => count($lineas)]);
                return $compraId;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $proveedor = $data['proveedor_tipo'] === 'existente' ? $data['proveedor_id']
                : DB::table('proveedores')->where('nombre', $this->nombreProveedor($data['proveedor_nombre']))->value('id');
            $registrada = DB::table('compras')->where('proveedor_id', $proveedor)->where('no_factura', $data['no_factura'])->value('id');
            if (!$registrada) {
                throw ValidationException::withMessages(['producto_codigo' => 'Un producto ya fue registrado. Selecciónalo del catálogo y vuelve a intentar.']);
            }

            return redirect()->route($prefijo . '.compras.show', $registrada)
                ->with('info', 'La factura ya fue registrada. Revisa los detalles de la compra existente.');
        } finally {
            if ($compra === null) {
                foreach ($adjuntos as $adjunto) {
                    DocumentoPrivado::descartar($adjunto['ruta']);
                }
            }
        }

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarMovimientoInventario(
                $bodega, $request->user(), 'La compra #' . $compra . ' registró una entrada en ' . $bodega->nombre . '.', (string) $compra
            )
        );

        return redirect()->route($prefijo . '.compras.index')
            ->with('success', 'Compra registrada y entrada aplicada a inventario (Bodega Principal).');
    }

    private function lineasCompra(array $data): array
    {
        $indices = array_keys($data['producto_tipo']);
        foreach (['producto_codigo', 'cantidad', 'precio_unitario'] as $campo) {
            if (array_keys($data[$campo]) !== $indices) {
                throw ValidationException::withMessages([$campo => 'Los datos deben corresponder a cada línea de compra.']);
            }
        }

        $total = 0;
        $lineas = [];
        foreach ($indices as $i) {
            $nuevo = $data['producto_tipo'][$i] === 'nuevo';
            $campoCodigo = $nuevo ? 'producto_codigo_nuevo' : 'producto_codigo';
            $codigo = trim($data[$campoCodigo][$i] ?? '');
            if ($nuevo) {
                $codigo = mb_strtoupper(preg_replace('/\s+/', '', $codigo));
            }

            $clave = mb_strtoupper($codigo);
            if ($codigo === '' || isset($lineas[$clave])) {
                throw ValidationException::withMessages(["{$campoCodigo}.{$i}" => 'Cada línea necesita un código de producto único.']);
            }

            $catalogo = null;
            if ($nuevo) {
                $nombre = trim($data['producto_nombre'][$i] ?? '');
                $categoria = trim($data['producto_categoria'][$i] ?? '');
                if ($nombre === '' || $categoria === '') {
                    throw ValidationException::withMessages(["producto_nombre.{$i}" => 'El producto nuevo necesita nombre y categoría.']);
                }
                $catalogo = [
                    'nombre' => mb_strtoupper($nombre),
                    'categoria' => $categoria,
                    'unidad_medida' => $data['producto_unidad'][$i] ?? 'UND',
                    'vida_util_meses' => $data['producto_vida_util_meses'][$i] ?? app(\App\Sistema\Ajustes::class)->general('vida_util'),
                    'descripcion' => $data['producto_descripcion'][$i] ?? null,
                ];
            }

            [$enteros, $fraccion] = array_pad(explode('.', (string) $data['precio_unitario'][$i], 2), 2, '');
            $precio = (int) $enteros * 100 + (int) str_pad($fraccion, 2, '0');
            $cantidad = (int) $data['cantidad'][$i];
            $total += $precio * $cantidad;
            if ($total > 999999999999) {
                throw ValidationException::withMessages(['precio_unitario' => 'El total supera el máximo permitido por factura.']);
            }

            $lineas[$clave] = ['codigo' => $codigo, 'nuevo' => $catalogo, 'cantidad' => $cantidad, 'precio' => $precio, 'indice' => $i];
        }

        ksort($lineas, SORT_STRING);
        return [$lineas, $total];
    }

    private function decimal(int $centavos): string
    {
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    private function proveedor(array $data): int
    {
        if ($data['proveedor_tipo'] === 'existente') {
            return (int) $data['proveedor_id'];
        }

        $nombre = $this->nombreProveedor($data['proveedor_nombre']);
        $consulta = DB::table('proveedores')->whereRaw('UPPER(nombre) = ?', [$nombre]);
        $id = $consulta->value('id');
        if ($id === null) {
            DB::table('proveedores')->insertOrIgnore(['nombre' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('proveedores')->where('nombre', $nombre)->lockForUpdate()->value('id');
        }

        return (int) $id;
    }

    private function nombreProveedor(string $nombre): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($nombre)));
    }

    private function autorizarConsulta(): void
    {
        abort_unless($this->bodegaAccess->canViewCompras(auth()->user()), 403);
    }

    private function rutaPrefijo(): string
    {
        return auth()->user()->rolOperativo() === 2 ? 'operador' : 'admin';
    }

    public function show(string $id)
    {
        $this->autorizarConsulta();
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
                $a->url = route($this->rutaPrefijo() . '.compras.archivo', ['compra' => $a->compra_id, 'archivo' => $a->id]);
                return $a;
            });

        return view('admin.compras.show', compact('compra', 'detalles', 'archivos'));
    }
    public function archivo(string $compra, string $archivo)
    {
        $this->autorizarConsulta();
        $adjunto = DB::table('compra_archivos')->where('compra_id', $compra)->where('id', $archivo)->first();
        abort_unless($adjunto, 404);

        return DocumentoPrivado::responder($adjunto->ruta, $adjunto->nombre_original);
    }
}
