<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Colaborador;
use App\Models\Bodega;
use App\Models\AsignacionMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Services\AsignacionVidaUtilService;
use App\Services\BodegaAccessService;
use App\Services\InventarioStockService;
use Carbon\Carbon;

class AsignacionInventarioController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stockService
    ) {
    }

    public function index()
    {
        $user = auth()->user();
        $routePrefix = $user->role_id == 2 ? 'operador' : 'admin';

        $query = AsignacionInventario::with(['colaborador', 'producto', 'bodega'])->latest();

        if ((int) $user->role_id !== 1) {
            $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
            if ($visibleBodegaIds === []) {
                $query->whereRaw('1 = 0');
            } elseif (is_array($visibleBodegaIds)) {
                $query->whereIn('bodega_id', $visibleBodegaIds);
            }
        }

        $asignaciones = $query->get();
        $asignacionesPorColaborador = $asignaciones
            ->groupBy('colaborador_codigo')
            ->map(function ($items) {
                $ordenadas = $items->sortByDesc('fecha')->values();
                $colaborador = optional($ordenadas->first())->colaborador;

                return [
                    'colaborador_codigo' => $ordenadas->first()->colaborador_codigo,
                    'colaborador_nombre' => $colaborador->nombre ?? 'Sin nombre',
                    'asignaciones' => $ordenadas,
                    'total_activo' => $ordenadas->where('estado', 'Activa')->sum('cantidad_asignada'),
                ];
            })
            ->values();

        $movimientos = collect();
        if (Schema::hasTable('asignacion_movimientos')) {
            $movimientos = AsignacionMovimiento::with(['asignacion.colaborador', 'user'])
                ->when((int) $user->role_id !== 1, function ($query) use ($user) {
                    $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
                    if ($visibleBodegaIds === []) {
                        $query->whereRaw('1 = 0');
                    } elseif (is_array($visibleBodegaIds)) {
                        $query->whereHas('asignacion', fn ($q) => $q->whereIn('bodega_id', $visibleBodegaIds));
                    }
                })
                ->latest()
                ->limit(30)
                ->get();
        }

        return view('admin.asignaciones.index', compact('asignacionesPorColaborador', 'routePrefix', 'movimientos'));
    }

    public function create()
    {
        $user = auth()->user();
        $editableBodegaIds = (int) $user->role_id === 1
            ? null
            : ($user->bodega_id ? [(int) $user->bodega_id] : []);

        $inventarios = Inventario::with('producto', 'bodega')
            ->when(is_array($editableBodegaIds), fn ($q) => $editableBodegaIds === []
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('bodega_id', $editableBodegaIds))
            ->get();
        $colaboradores = Colaborador::where('estado', 'Activo')->get();
        $bodegas = Bodega::query()
            ->when(is_array($editableBodegaIds), fn ($q) => $editableBodegaIds === []
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('id', $editableBodegaIds))
            ->orderBy('nombre')
            ->get();

        $aprobadores = [
            'Gerencia',
            'RRHH',
            'Jefe de área',
            'Supervisor'
        ];

        return view('admin.asignaciones.create', compact(
            'inventarios',
            'colaboradores',
            'bodegas',
            'aprobadores'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'colaborador_codigo' => 'required|exists:colaboradores,codigo',
            'fecha' => 'required|date',
            'costo_unitario' => 'nullable|numeric',
            'aprobado_por' => 'required|string',
            'medio_solicitud' => 'required|in:WhatsApp,Correo',
            'imagen' => 'nullable|image',
            'observaciones' => 'nullable|string',
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_codigo' => ['required', 'exists:productos,codigo'],
            'items.*.bodega_id' => ['required', 'exists:bodegas,id'],
            'items.*.cantidad_asignada' => ['required', 'integer', 'min:1'],
            'items.*.es_reemplazo' => ['nullable', 'boolean'],
            'items.*.fecha_dano' => ['nullable', 'date'],
        ]);

        // Imagen compartida en todos los ítems enviados
        $imagenPath = null;
        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->store('asignaciones', 'public');
        }

        $items = collect($data['items'])->values();

        // Validación de stock consolidada por producto + bodega para evitar sobreasignación en lote.
        $solicitudesAgrupadas = $items
            ->groupBy(fn ($item) => $item['producto_codigo'] . '|' . $item['bodega_id'])
            ->map(fn ($grupo) => [
                'producto_codigo' => $grupo->first()['producto_codigo'],
                'bodega_id' => (int) $grupo->first()['bodega_id'],
                'cantidad_total' => (int) $grupo->sum('cantidad_asignada'),
            ]);

        foreach ($solicitudesAgrupadas as $solicitud) {
            if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $solicitud['bodega_id'])) {
                abort(403);
            }

            $inventario = Inventario::query()
                ->where('producto_codigo', $solicitud['producto_codigo'])
                ->where('bodega_id', $solicitud['bodega_id'])
                ->first();

            if (!$inventario || (int) $inventario->cantidad < $solicitud['cantidad_total']) {
                return back()
                    ->withInput()
                    ->with('error', 'Stock insuficiente para uno o más productos.');
            }
        }

        foreach ($items as $item) {
            if (empty($item['es_reemplazo'])) {
                continue;
            }

            $asignacionActiva = AsignacionInventario::query()
                ->where('colaborador_codigo', $data['colaborador_codigo'])
                ->where('producto_codigo', $item['producto_codigo'])
                ->where('estado', 'Activa')
                ->where('cantidad_asignada', '>', 0)
                ->when((int) auth()->user()->role_id !== 1, fn ($query) => $query->where('bodega_id', auth()->user()->bodega_id))
                ->latest('fecha')
                ->first();

            if (!$asignacionActiva) {
                return back()
                    ->withInput()
                    ->with('error', 'Marcaste reemplazo por daño pero no existe una asignación activa previa para el producto seleccionado.');
            }
        }

        DB::transaction(function () use ($data, $items, $imagenPath) {
            $vidaUtilService = app(AsignacionVidaUtilService::class);
            foreach ($items as $item) {
                $this->stockService->descontar(
                    (int) $item['bodega_id'],
                    $item['producto_codigo'],
                    (int) $item['cantidad_asignada']
                );

                $inventario = Inventario::with('producto')
                    ->where('producto_codigo', $item['producto_codigo'])
                    ->where('bodega_id', $item['bodega_id'])
                    ->firstOrFail();

                $costoUnitario = $data['costo_unitario'] ?? null;
                if (empty($costoUnitario)) {
                    $ultimoCosto = DB::table('compra_detalles as cd')
                        ->join('compras as c', 'c.id', '=', 'cd.compra_id')
                        ->where('cd.producto_codigo', $item['producto_codigo'])
                        ->orderByDesc('c.fecha_compra')
                        ->orderByDesc('cd.id')
                        ->value('cd.precio_unitario');

                    $costoUnitario = $ultimoCosto ?? $inventario->producto->costo ?? 0;
                }

                $payload = [
                    'colaborador_codigo' => $data['colaborador_codigo'],
                    'producto_codigo' => $item['producto_codigo'],
                    'bodega_id' => (int) $item['bodega_id'],
                    'cantidad_asignada' => (int) $item['cantidad_asignada'],
                    'fecha' => $data['fecha'],
                    'costo_unitario' => $costoUnitario,
                    'aprobado_por' => $data['aprobado_por'],
                    'medio_solicitud' => $data['medio_solicitud'],
                    'imagen' => $imagenPath,
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => 'Activa',
                ];

                if (!empty($inventario->producto?->vida_util_meses)) {
                    $payload['fecha_vencimiento'] = Carbon::parse($data['fecha'])->addMonths($inventario->producto->vida_util_meses);
                }

                if (Schema::hasColumn('asignaciones_inventarios', 'user_id')) {
                    $payload['user_id'] = auth()->id();
                }

                $asignacion = AsignacionInventario::create($payload);

                if (Schema::hasTable('asignacion_movimientos')) {
                    AsignacionMovimiento::create([
                        'asignacion_inventario_id' => $asignacion->id,
                        'tipo' => 'Asignacion',
                        'cantidad' => $asignacion->cantidad_asignada,
                        'detalle' => 'Asignación inicial del producto.',
                        'user_id' => auth()->id(),
                    ]);
                }

                $vidaUtilService->registrarEstado($asignacion, 'activo', 'Producto asignado al colaborador.');

                if (!empty($item['es_reemplazo'])) {
                    $anterior = AsignacionInventario::with('producto')
                        ->where('colaborador_codigo', $data['colaborador_codigo'])
                        ->where('producto_codigo', $item['producto_codigo'])
                        ->where('estado', 'Activa')
                        ->where('id', '!=', $asignacion->id)
                        ->where('cantidad_asignada', '>', 0)
                        ->when((int) auth()->user()->role_id !== 1, fn ($query) => $query->where('bodega_id', auth()->user()->bodega_id))
                        ->latest('fecha')
                        ->first();

                    if ($anterior) {
                        $fechaDanio = !empty($item['fecha_dano'])
                            ? Carbon::parse($item['fecha_dano'])
                            : Carbon::parse($data['fecha']);

                        $vidaUtilService->procesarReemplazoPorDanio($anterior, $asignacion, $fechaDanio);

                        if (Schema::hasTable('asignacion_movimientos')) {
                            AsignacionMovimiento::create([
                                'asignacion_inventario_id' => $anterior->id,
                                'tipo' => 'Reemplazo',
                                'cantidad' => $anterior->cantidad_asignada,
                                'detalle' => 'Reemplazo por daño registrado al asignar nuevo producto.',
                                'user_id' => auth()->id(),
                            ]);
                        }
                    }
                }
            }
        });

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.asignaciones.pdf', $data['colaborador_codigo'])
            ->with('success', 'Asignaciones registradas correctamente.');
    }

    // 🔥 NUEVO: GENERAR HOJA PDF / IMPRIMIBLE
    public function pdf($codigo)
    {
        $colaborador = Colaborador::where('codigo', $codigo)->firstOrFail();
        $usuario = auth()->user();

        $asignaciones = AsignacionInventario::with('producto', 'bodega')
            ->where('colaborador_codigo', $codigo)
            ->when((int) $usuario->role_id !== 1, function ($query) use ($usuario) {
                $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($usuario);
                if ($visibleBodegaIds === []) {
                    $query->whereRaw('1 = 0');
                } elseif (is_array($visibleBodegaIds)) {
                    $query->whereIn('bodega_id', $visibleBodegaIds);
                }
            })
            ->get();

        if ($asignaciones->isEmpty()) {
            abort(403);
        }

        $total = $asignaciones->sum(function ($a) {
            return ($a->costo_unitario ?? 0) * $a->cantidad_asignada;
        });

        $asignadorNombre = $usuario?->name ?? 'No identificado';
        $bodegaAsignador = $usuario?->bodega?->nombre
            ?? optional($asignaciones->first()?->bodega)->nombre
            ?? 'No definida';

        return view('admin.asignaciones.pdf', compact(
            'colaborador',
            'asignaciones',
            'total',
            'asignadorNombre',
            'bodegaAsignador'
        ));
    }

    public function uploadPdfFirmado(Request $request, AsignacionInventario $asignacion)
    {
        if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $asignacion->bodega_id)) {
            abort(403);
        }

        $data = $request->validate([
            'pdf_firmado' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($asignacion->pdf_firmado) {
            Storage::disk('public')->delete($asignacion->pdf_firmado);
        }

        $path = $data['pdf_firmado']->store('asignaciones/firmados', 'public');
        $asignacion->update(['pdf_firmado' => $path]);

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Documento firmado cargado correctamente.');
    }

    public function devolver(Request $request, AsignacionInventario $asignacion)
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $asignacion->bodega_id)) {
            abort(403);
        }

        if ($asignacion->estado !== 'Activa') {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'La asignación ya no está activa.');
        }

        $data = $request->validate([
            'cantidad_devuelta' => ['required', 'integer', 'min:1'],
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($asignacion, $data) {
            $asignacion = AsignacionInventario::query()
                ->whereKey($asignacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $cantidadDevuelta = min((int) $data['cantidad_devuelta'], (int) $asignacion->cantidad_asignada);
            if ($cantidadDevuelta <= 0) {
                return;
            }

            $asignacion->cantidad_asignada = (int) $asignacion->cantidad_asignada - $cantidadDevuelta;
            if ((int) $asignacion->cantidad_asignada <= 0) {
                $asignacion->cantidad_asignada = 0;
                $asignacion->estado = 'Devuelta';
            }
            $asignacion->save();

            $this->stockService->incrementar(
                (int) $asignacion->bodega_id,
                $asignacion->producto_codigo,
                $cantidadDevuelta
            );

            if (Schema::hasTable('asignacion_movimientos')) {
                AsignacionMovimiento::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo' => 'Devolucion',
                    'cantidad' => $cantidadDevuelta,
                    'detalle' => $data['detalle_devolucion'] ?? 'Devolución de producto.',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Devolución registrada correctamente.');
    }

    public function devolverLote(Request $request)
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        $data = $request->validate([
            'devoluciones' => ['required', 'array', 'min:1'],
            'devoluciones.*' => ['required', 'integer', 'min:1'],
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = collect(array_keys($data['devoluciones']))->map(fn ($id) => (int) $id)->filter()->values();
        $asignaciones = AsignacionInventario::query()
            ->whereIn('id', $ids)
            ->when((int) auth()->user()->role_id !== 1, fn ($query) => $query->where('bodega_id', auth()->user()->bodega_id))
            ->get()
            ->keyBy('id');

        if ($asignaciones->isEmpty()) {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'No se encontraron asignaciones para devolver.');
        }

        DB::transaction(function () use ($data, $asignaciones) {
            foreach ($data['devoluciones'] as $id => $cantidadSolicitada) {
                $id = (int) $id;
                $cantidadSolicitada = (int) $cantidadSolicitada;
                if (!$asignaciones->has($id)) {
                    continue;
                }

                $asignacion = AsignacionInventario::query()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$asignacion || $asignacion->estado !== 'Activa') {
                    continue;
                }

                if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $asignacion->bodega_id)) {
                    continue;
                }

                $cantidadDevuelta = min($cantidadSolicitada, (int) $asignacion->cantidad_asignada);
                if ($cantidadDevuelta <= 0) {
                    continue;
                }

                $asignacion->cantidad_asignada = (int) $asignacion->cantidad_asignada - $cantidadDevuelta;
                if ((int) $asignacion->cantidad_asignada <= 0) {
                    $asignacion->cantidad_asignada = 0;
                    $asignacion->estado = 'Devuelta';
                }
                $asignacion->save();

                $this->stockService->incrementar(
                    (int) $asignacion->bodega_id,
                    $asignacion->producto_codigo,
                    $cantidadDevuelta
                );

                if (Schema::hasTable('asignacion_movimientos')) {
                    AsignacionMovimiento::create([
                        'asignacion_inventario_id' => $asignacion->id,
                        'tipo' => 'Devolucion',
                        'cantidad' => $cantidadDevuelta,
                        'detalle' => $data['detalle_devolucion'] ?? 'Devolución múltiple.',
                        'user_id' => auth()->id(),
                    ]);
                }
            }
        });

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Devolución múltiple registrada correctamente.');
    }

    public function devolverTodoColaborador(Request $request, string $colaboradorCodigo)
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        $data = $request->validate([
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = AsignacionInventario::query()
            ->where('colaborador_codigo', $colaboradorCodigo)
            ->where('estado', 'Activa');

        if ((int) auth()->user()->role_id !== 1) {
            $query->where('bodega_id', auth()->user()->bodega_id);
        }

        $asignaciones = $query->get();

        if ($asignaciones->isEmpty()) {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'No hay asignaciones activas para devolver.');
        }

        DB::transaction(function () use ($asignaciones, $data) {
            foreach ($asignaciones as $asignacionPendiente) {
                $asignacion = AsignacionInventario::query()
                    ->whereKey($asignacionPendiente->id)
                    ->lockForUpdate()
                    ->first();

                if (!$asignacion || $asignacion->estado !== 'Activa') {
                    continue;
                }

                if (!$this->bodegaAccess->canModifyStock(auth()->user(), (int) $asignacion->bodega_id)) {
                    continue;
                }

                $cantidadDevuelta = (int) $asignacion->cantidad_asignada;
                if ($cantidadDevuelta <= 0) {
                    continue;
                }

                $asignacion->cantidad_asignada = 0;
                $asignacion->estado = 'Devuelta';
                $asignacion->save();

                $this->stockService->incrementar(
                    (int) $asignacion->bodega_id,
                    $asignacion->producto_codigo,
                    $cantidadDevuelta
                );

                if (Schema::hasTable('asignacion_movimientos')) {
                    AsignacionMovimiento::create([
                        'asignacion_inventario_id' => $asignacion->id,
                        'tipo' => 'Devolucion',
                        'cantidad' => $cantidadDevuelta,
                        'detalle' => $data['detalle_devolucion'] ?? 'Devolución total por colaborador.',
                        'user_id' => auth()->id(),
                    ]);
                }
            }
        });

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Se devolvió todo el inventario activo del colaborador.');
    }
}
