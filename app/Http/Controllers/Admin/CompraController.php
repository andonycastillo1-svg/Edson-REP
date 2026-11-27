<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Auditoria;
use App\Sistema\Presentacion;
use App\Sistema\Permisos;
use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Sistema\ConsultaCampos;
use App\Models\Bodega;
use App\Models\Consolidado;
use App\Services\DistribucionCompras;
use App\Services\DocumentoPrivado;
use App\Services\FlujoAcceso;
use App\Services\PlanCompras;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BodegaAccessService;
use App\Services\AccesoInventarioOperativo;
use App\Services\InventarioStockService;
use App\Services\NotificacionService;

class CompraController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private AccesoInventarioOperativo $inventario,
        private InventarioStockService $stockService,
        private PlanCompras $plan,
        private DistribucionCompras $distribuciones
    ) {
    }

    public function index(Request $request)
    {
        $permiso = $request->boolean('xlsx') ? 'compras.datos_exportar' : 'compras.consultar';
        $consulta = $this->facturasAutorizadas($permiso, auditar: true)
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->select('c.*', 'p.nombre as proveedor_nombre');
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:190'],
            'por_pagina' => ['nullable', Rule::in([10, 15, 25, 50])],
        ]);
        $campos = app(ConsultaCampos::class);
        $campos->filtrar($consulta, $request, 'compras', 'c.id');
        $busqueda = $datos['buscar'] ?? null;
        $campos->buscar($consulta, $busqueda, ['c.id', 'c.fecha_compra', 'c.no_factura', 'p.nombre', 'c.proyecto', 'c.total_factura']);
        if ($request->boolean('xlsx')) {
            return app(ExportacionesOperativas::class)->compras($consulta);
        }
        $compras = $consulta->orderByDesc('c.id')->paginate((int) ($datos['por_pagina'] ?? 10))->withQueryString();
        $tablaCampos = app(Presentacion::class)->operativa('compras', $request->user(), $compras->pluck('id'));

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
            'tablaCampos',
            'proveedores',
            'productos',
            'categorias'
        ) + $this->consolidadosParaCompra());
    }

    public function create()
    {
        $this->inventario->autorizar(auth()->user(), 'compras.crear', [(int) $this->bodegaAccess->principal()?->id]);
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
        ) + $this->consolidadosParaCompra());
    }

    private function consolidadosParaCompra(): array
    {
        $usuario = auth()->user();
        $puedeDistribuir = $this->inventario->tiene($usuario, 'compras.vincular');
        $candidatos = $puedeDistribuir
            ? Consolidado::where('estado', 'aprobado')->orderBy('nombre')->get(['id', 'nombre', 'plan_compra']) : collect();
        $facturas = $this->plan->facturas($candidatos);
        $consolidados = $candidatos->reject(fn ($fila) => $facturas->get($fila->id, collect())->contains('distribucion_confirmada_en', null));
        $pendientes = $this->plan->avance($consolidados)->where('pendiente', '>', 0)->groupBy('consolidado_id');

        return ['consolidados' => $consolidados->whereIn('id', $pendientes->keys()), 'pendientes' => $pendientes];
    }

    public function store(Request $request)
    {
        $bodega = $this->bodegaAccess->principal() ?? abort(404, 'No hay bodega principal configurada.');
        $this->inventario->autorizar($request->user(), 'compras.crear', [(int) $bodega->id]);

        $data = $request->validate([
            'fecha_compra' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . today()->subDays(7)->toDateString(), 'before_or_equal:' . today()->toDateString()],
            'consolidados' => ['nullable', 'array', 'max:200'],
            'consolidados.*' => ['required', 'integer', 'distinct'],
            'fecha_ingreso' => ['nullable', 'date', 'before_or_equal:now'],
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
        ] + DistribucionCompras::reglas());

        app(Presentacion::class)->validar($request, 'compras', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'compras', true);
        $permiso = !empty($data['consolidados']) || !empty($data['distribucion']) ? 'compras.vincular' : 'compras.crear';
        $this->inventario->autorizar($request->user(), $permiso, [(int) $bodega->id]);
        if (!empty($data['consolidados']) && app(Presentacion::class)->nativos('compras')['autorizado_por']['visible']) {
            $request->validate(['autorizado_por' => ['required']], [
                'autorizado_por.required' => 'Indica quién autorizó la compra del consolidado.',
            ]);
        }
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

            $compra = DB::transaction(function () use ($data, $bodega, $lineas, $total, $adjuntos, $request, $campos, $valores, $bloquearPrincipal, $permiso) {
                if ($bloquearPrincipal) {
                    app(\App\Sistema\Seguridad::class)->bloquear();
                    app(\App\Sistema\Ajustes::class)->refrescar('general');
                    if ((int) $this->bodegaAccess->principal()?->id !== (int) $bodega->id) {
                        throw ValidationException::withMessages(['bodega_id' => 'La bodega principal cambió. Vuelve a enviar la compra.']);
                    }
                }
                $alcance = $this->inventario->autorizar($request->user(), $permiso, [(int) $bodega->id], bloquear: true);
                $proveedorId = $this->proveedor($data);
                $compraId = DB::table('compras')->insertGetId([
                    'bodega_id' => $bodega->id,
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

                $consolidados = empty($data['consolidados']) ? collect()
                    : $this->plan->aprobados($data['consolidados'], bloquear: true);
                $distribucion = [];
                if ($consolidados->isNotEmpty() || !empty($data['distribucion'])) {
                    $facturadas = collect($lineas)->mapWithKeys(function ($linea) {
                        $clave = bin2hex($linea['codigo']);

                        return [$clave => $linea + ['clave' => $clave]];
                    });
                    $distribucion = $this->distribuciones->validar($consolidados, $facturadas, $data['distribucion'] ?? []);
                }

                $detalles = [];
                $ingreso = isset($data['fecha_ingreso']) ? \Illuminate\Support\Carbon::parse($data['fecha_ingreso']) : now();
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

                    $detalles[bin2hex($codigo)] = DB::table('compra_detalles')->insertGetId([
                        'compra_id' => $compraId,
                        'producto_codigo' => $codigo,
                        'cantidad' => $linea['cantidad'],
                        'precio_unitario' => $this->decimal($linea['precio']),
                        'valor_total' => $this->decimal($linea['cantidad'] * $linea['precio']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->stockService->incrementar((int) $bodega->id, $codigo, $linea['cantidad'], $linea['precio'] / 100, $ingreso);

                    DB::table('movimientos')->insert([
                        'producto_codigo' => $codigo,
                        'bodega_destino_id' => $bodega->id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => $linea['cantidad'],
                        'fecha' => $ingreso,
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

                foreach ($consolidados as $consolidado) {
                    DB::table('compra_consolidado')->insert(['compra_id' => $compraId, 'consolidado_id' => $consolidado->id]);
                }
                if ($consolidados->isNotEmpty()) {
                    $this->distribuciones->guardar($compraId, $distribucion, $detalles);
                }
                $campos->guardar('compras', $compraId, $valores);
                $this->inventario->registrar($alcance, $permiso, $compraId);
                app(Auditoria::class)->registrar('compras', 'registrar', $compraId, null, ['bodega_id' => $bodega->id, 'lineas' => count($lineas)]);
                if ($consolidados->isNotEmpty()) {
                    DB::afterCommit(fn () => $consolidados->each(fn ($consolidado) => app(NotificacionService::class)->safeAction(
                        fn ($servicio) => $servicio->notificarCompraVinculada($consolidado, $request->user(), $compraId)
                    )));
                }
                return $compraId;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            $proveedor = $data['proveedor_tipo'] === 'existente' ? $data['proveedor_id']
                : DB::table('proveedores')->where('nombre', $this->nombreProveedor($data['proveedor_nombre']))->value('id');
            $registrada = DB::table('compras')->where('proveedor_id', $proveedor)->where('no_factura', $data['no_factura'])->value('id');
            if (!$registrada) {
                throw ValidationException::withMessages(['producto_codigo' => 'Un producto ya fue registrado. Selecciónalo del catálogo y vuelve a intentar.']);
            }

            return app(Permisos::class)->ruta($request->user(), $prefijo.'.compras.show')
                ? redirect()->route($prefijo . '.compras.show', $registrada)->with('info', 'La factura ya fue registrada.')
                : redirect()->route($prefijo . '.compras.create')
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

        return redirect()->route(app(Permisos::class)->ruta($request->user(), $prefijo.'.compras.index')
            ? $prefijo.'.compras.index' : $prefijo.'.compras.create')
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

    private function facturasAutorizadas(string $permiso, bool $auditar = false): Builder
    {
        $central = $this->bodegaAccess->principal();
        $consulta = DB::table('compras as c');
        $alcances = $this->inventario->alcances(auth()->user(), $permiso)->filter(fn ($alcance) => $alcance->contiene([(int) $central?->id]));
        $alcance = $alcances->firstWhere('historico', true) ?? $alcances->first() ?? abort(403);
        if ($auditar) {
            $this->inventario->registrar($alcance, $permiso, 'central');
        }

        return $consulta->where(fn ($q) => $q->where('c.bodega_id', $central?->id)
            ->when($alcance->historico, fn ($q) => $q->orWhereNull('c.bodega_id')));
    }

    private function rutaPrefijo(): string
    {
        return AccesoInventarioOperativo::prefijo(auth()->user());
    }

    public function show(string $id)
    {
        $compra = $this->facturasAutorizadas('compras.consultar', auditar: true)
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

        $tablaCampos = app(Presentacion::class)->operativa('productos', auth()->user(), $detalles->pluck('producto_codigo'));

        $acceso = app(FlujoAcceso::class);
        $usuario = auth()->user();
        $vinculos = $this->vinculos($id);
        $consolidados = $this->inventario->tiene($usuario, 'compras.vincular')
            || $acceso->puede($usuario, 'compras')
            ? Consolidado::whereIn('id', $vinculos)->orderBy('id')->get(['id', 'nombre', 'plan_compra'])
            : ($acceso->puede($usuario, 'consolidados') ? $acceso->consolidados($usuario)->whereIn('id', $vinculos)
                ->orderBy('id')->get(['id', 'nombre', 'plan_compra']) : collect());
        $puedeVerConsolidado = $acceso->puede($usuario, 'consolidados');
        $distribucion = DB::table('compra_detalle_consolidado as distribucion')->whereIn('compra_detalle_id', $detalles->pluck('id'))
            ->join('consolidado_lineas as linea', 'linea.id', '=', 'distribucion.consolidado_linea_id')
            ->whereIn('linea.consolidado_id', $consolidados->pluck('id'))
            ->get(['linea.consolidado_id', 'distribucion.compra_detalle_id', 'distribucion.cantidad'])->groupBy('consolidado_id');
        $conciliar = $consolidados->isNotEmpty() && !$compra->distribucion_confirmada_en && $this->inventario->tiene($usuario, 'compras.conciliar');
        $pendientes = $conciliar ? $this->plan->avance($consolidados)->keyBy(fn ($fila) => $fila['consolidado_id'].'|'.$fila['codigo']) : collect();

        return view('admin.compras.show', compact('compra', 'detalles', 'archivos', 'tablaCampos', 'consolidados', 'puedeVerConsolidado',
            'distribucion', 'conciliar', 'pendientes'));
    }

    public function conciliar(Request $request, string $compra)
    {
        $usuario = $request->user();
        $datos = $request->validate(['confirmar' => ['accepted']] + DistribucionCompras::reglas());

        $guardada = DB::transaction(function () use ($compra, $datos, $usuario) {
            $alcance = $this->inventario->autorizar($usuario, 'compras.conciliar', [(int) $this->bodegaAccess->principal()?->id], bloquear: true);
            abort_unless($this->facturasAutorizadas('compras.conciliar')->where('c.id', $compra)->exists(), 404);
            $consolidados = Consolidado::whereIn('id', $this->vinculos($compra))->orderBy('id')->lockForUpdate()->get();
            $factura = DB::table('compras')->where('id', $compra)->lockForUpdate()->first();
            abort_unless($factura && $consolidados->isNotEmpty(), 404);
            if ($factura->distribucion_confirmada_en) {
                return false;
            }

            $lineas = DB::table('compra_detalles')->where('compra_id', $compra)->orderBy('id')->get();
            $facturadas = $lineas->mapWithKeys(fn ($linea) => [$linea->id => [
                'clave' => $linea->id, 'codigo' => $linea->producto_codigo, 'cantidad' => $linea->cantidad,
            ]]);
            $distribucion = $this->distribuciones->validar($consolidados, $facturadas, $datos['distribucion'] ?? [], historica: true);
            $this->distribuciones->guardar((int) $compra, $distribucion, $lineas->pluck('id', 'id')->all());
            $this->inventario->registrar($alcance, 'compras.conciliar', $compra);
            app(Auditoria::class)->registrar('compras', 'conciliar_distribucion', $compra, null, [
                'consolidados' => $consolidados->modelKeys(), 'distribucion' => $distribucion,
            ]);

            return true;
        }, 3);

        return redirect()->route($this->rutaPrefijo().'.compras.show', $compra)
            ->with('success', $guardada ? 'Distribución histórica conciliada.' : 'La distribución de esta factura ya está confirmada.');
    }

    private function vinculos(string $compra): Builder
    {
        return DB::table('compra_consolidado')->where('compra_id', $compra)->select('consolidado_id');
    }

    public function archivo(string $compra, string $archivo)
    {
        abort_unless($this->facturasAutorizadas('compras.archivos', auditar: true)->where('c.id', $compra)->exists(), 404);
        $adjunto = DB::table('compra_archivos')->where('compra_id', $compra)->where('id', $archivo)->first();
        abort_unless($adjunto, 404);

        return DocumentoPrivado::responder($adjunto->ruta, $adjunto->nombre_original);
    }
}
