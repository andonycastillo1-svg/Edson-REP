<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Colaborador;
use App\Models\Bodega;
use App\Models\AsignacionMovimiento;
use App\Models\AsignacionInventarioArchivo;
use App\Models\AlertaReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AsignacionVidaUtilService;
use App\Services\NotificacionService;
use Carbon\Carbon;
use App\Services\AsignacionReplacementService;
use App\Services\BodegaAccessService;
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use App\Services\SolicitudInventarioService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignacionInventarioController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stock,
    ) {}

    public function index()
    {
        $usuario = auth()->user();
        $routePrefix = $this->asignacionesRoutePrefix();
        $puedeCrearAsignaciones = in_array((int) $usuario->role_id, [1, 2], true);
        $puedeGestionarDevoluciones = $puedeCrearAsignaciones;
        $puedeSubirEvidencias = in_array((int) $usuario->role_id, [1, 2, 3], true);

        $relaciones = ['colaborador', 'producto', 'bodega', 'user'];

        if (Schema::hasTable('asignacion_inventario_archivos')) {
            $relaciones[] = 'evidencias.subidoPor';
            $relaciones[] = 'pdfAsignacionFirmado.subidoPor';
        }

        $query = AsignacionInventario::with($relaciones)->latest();

        if (Schema::hasColumn('asignaciones_inventarios', 'user_id')) {
            if ((int) $usuario->role_id === 2) {
                $query->where('user_id', $usuario->id);
            } elseif ((int) $usuario->role_id === 3) {
                $query->whereIn('user_id', $usuario->almacenistasAsignados()->select('users.id'));
            } elseif ((int) $usuario->role_id !== 1) {
                $query->whereRaw('1 = 0');
            }
        } elseif ((int) $usuario->role_id === 3) {
            // Sin el campo creador no es posible demostrar la relación; se niega por seguridad.
            $query->whereRaw('1 = 0');
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
            $movimientosQuery = AsignacionMovimiento::with(['asignacion.colaborador', 'user'])
                ->latest()
                ->limit(30);

            if ((int) $usuario->role_id === 2) {
                $movimientosQuery->whereHas('asignacion', fn ($query) => $query->where('user_id', $usuario->id));
            } elseif ((int) $usuario->role_id === 3) {
                $almacenistaIds = $usuario->almacenistasAsignados()->pluck('users.id');
                $movimientosQuery->whereHas('asignacion', fn ($query) => $query->whereIn('user_id', $almacenistaIds));
            } elseif ((int) $usuario->role_id !== 1) {
                $movimientosQuery->whereRaw('1 = 0');
            }

            $movimientos = $movimientosQuery->get();
        }

        $pdfsDevolucionFirmados = collect();

        if (Schema::hasTable('asignacion_inventario_archivos') && $movimientos->isNotEmpty()) {
            $gruposDevolucion = $movimientos
                ->where('tipo', 'Devolucion')
                ->pluck('grupo_devolucion')
                ->filter()
                ->unique()
                ->values();

            $pdfsDevolucionFirmados = AsignacionInventarioArchivo::with('subidoPor')
                ->whereIn('grupo_devolucion', $gruposDevolucion)
                ->where('tipo_documento', 'devolucion_firmada')
                ->latest('id')
                ->get()
                ->unique('grupo_devolucion')
                ->keyBy('grupo_devolucion');
        }

        return view('admin.asignaciones.index', compact(
            'asignacionesPorColaborador',
            'routePrefix',
            'movimientos',
            'pdfsDevolucionFirmados',
            'puedeCrearAsignaciones',
            'puedeGestionarDevoluciones',
            'puedeSubirEvidencias'
        ));
    }

    public function create()
    {
        $inventariosQuery = Inventario::with('producto', 'bodega')
            ->whereIn('stock_tipo', ['nuevo', 'usado'])->where('cantidad', '>', 0);
        $bodegasQuery = Bodega::query();

        if ((int) auth()->user()->role_id !== 1) {
            $bodegaId = (int) (auth()->user()->bodega_id ?? 0);
            $inventariosQuery->where('bodega_id', $bodegaId);
            $bodegasQuery->where('id', $bodegaId);
        }

        $inventarios = $inventariosQuery->get();
        $colaboradores = Colaborador::where('estado', 'Activo')->get();
        $bodegas = $bodegasQuery->get();

        $aprobadores = [
            'Gerencia',
            'RRHH',
            'Jefe de área',
            'Supervisor',
        ];

        return view('admin.asignaciones.create', compact(
            'inventarios',
            'colaboradores',
            'bodegas',
            'aprobadores'
        ));
    }

    public function asignacionesActivas(Request $request, AsignacionReplacementService $reemplazos)
    {
        $data = $request->validate([
            'colaborador_codigo' => ['required', 'exists:colaboradores,codigo'],
            'producto_codigo' => ['required', 'exists:productos,codigo'],
        ]);

        return response()->json([
            'data' => $reemplazos->activas($request->user(), $data['colaborador_codigo'], $data['producto_codigo']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'solicitud_id' => ['nullable', 'uuid'],
            'colaborador_codigo' => ['required', Rule::exists('colaboradores', 'codigo')->where('estado', 'Activo')],
            'fecha' => 'required|date',
            'costo_unitario' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'aprobado_por' => 'required|string',
            'medio_solicitud' => 'required|in:WhatsApp,Correo',
            'imagen' => 'nullable|image|max:10240',
            'observaciones' => 'nullable|string',
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.producto_codigo' => ['required', 'exists:productos,codigo'],
            'items.*.bodega_id' => ['required', 'exists:bodegas,id'],
            'items.*.cantidad_asignada' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.stock_tipo' => ['nullable', 'in:nuevo,usado'],
            'items.*.asignacion_anterior_id' => ['nullable', 'integer'],
            'items.*.es_reemplazo' => ['nullable', 'boolean'],
            'items.*.fecha_dano' => ['nullable', 'date'],
        ]);

        $items = collect($data['items'])->sortBy('producto_codigo')->values();

        foreach ($items as $item) {
            abort_unless($this->bodegaAccess->canModifyStock($request->user(), (int) $item['bodega_id']), 403);
        }

        $imagenPath = $request->hasFile('imagen')
            ? DocumentoPrivado::guardar($request->file('imagen'), 'asignaciones') : null;
        $grupoAsignacion = (string) Str::uuid();

        try {
            $alertasRrhhIds = DB::transaction(function () use ($data, $items, $imagenPath, $grupoAsignacion, $request) {
                SolicitudInventarioService::registrar('asignacion', $data['solicitud_id'] ?? null);
                $colaborador = Colaborador::whereKey($data['colaborador_codigo'])->lockForUpdate()->firstOrFail();
                if ($colaborador->estado !== 'Activo') {
                    throw ValidationException::withMessages(['colaborador_codigo' => 'El colaborador no está activo.']);
                }

                $vidaUtilService = app(AsignacionVidaUtilService::class);
                $alertas = [];

                foreach ($items as $item) {
                    $tipo = $item['stock_tipo'] ?? 'nuevo';
                    $producto = Producto::whereKey($item['producto_codigo'])->firstOrFail();
                    $anterior = $this->reemplazoAutorizado($request, $data['colaborador_codigo'], $item);

                    $costoUnitario = $data['costo_unitario'] ?? null;

                    if ($costoUnitario === null) {
                        $ultimoCosto = DB::table('compra_detalles as cd')
                            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
                            ->where('cd.producto_codigo', $item['producto_codigo'])
                            ->orderByDesc('c.fecha_compra')
                            ->orderByDesc('cd.id')
                            ->value('cd.precio_unitario');

                        $costoUnitario = $ultimoCosto ?? 0;
                    }

                    $payload = [
                        'grupo_asignacion' => $grupoAsignacion,
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
                        'estado_evidencia' => 'pendiente',
                        'stock_tipo' => $tipo,
                        'vida_util_original_meses' => $producto->vida_util_meses,
                    ];

                    $payload['user_id'] = $request->user()->id;
                    $asignacion = AsignacionInventario::create($payload);
                    $unidades = $this->stock->asignar($asignacion, $tipo);
                    $vidaRestante = $tipo === 'nuevo' ? $producto->vida_util_meses : null;
                    if ($unidades->isNotEmpty()) {
                        $vidas = $unidades->pluck('vida_util_restante_segundos');
                        $segundos = $vidas->contains(null) ? null : $vidas->min();
                        $vidaRestante = $segundos === null ? null : intdiv($segundos, 30 * 86400);
                        $asignacion->fecha_vencimiento = $segundos === null ? null : Carbon::parse($data['fecha'])->addSeconds($segundos);
                    } elseif ($vidaRestante !== null) {
                        $asignacion->fecha_vencimiento = Carbon::parse($data['fecha'])->addMonthsNoOverflow($vidaRestante);
                    }
                    $asignacion->vida_util_restante_meses = $vidaRestante;
                    $asignacion->save();

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

                    if ($anterior) {
                        $fechaDanio = Carbon::parse($item['fecha_dano'] ?? $data['fecha']);
                        if ($fechaDanio->lt($anterior->fecha) || $fechaDanio->gt(now()->endOfDay())) {
                            throw ValidationException::withMessages(['items' => 'La fecha de daño debe estar entre la asignación anterior y hoy.']);
                        }

                        $cantidad = $asignacion->cantidad_asignada;
                        if ($cantidad > $anterior->cantidad_asignada) {
                            throw ValidationException::withMessages(['items' => 'El reemplazo supera la cantidad activa anterior.']);
                        }

                        $this->stock->devolver($anterior, $cantidad, 'danado', 'Reemplazo por daño.');
                        $anterior->cantidad_asignada -= $cantidad;
                        $alerta = $vidaUtilService->procesarReemplazoPorDanio(
                            $anterior, $asignacion, $fechaDanio, $anterior->cantidad_asignada === 0
                        );
                        if ($alerta) {
                            $alertas[] = $alerta->id;
                        }

                        $anterior->save();
                        AsignacionMovimiento::create([
                            'asignacion_inventario_id' => $anterior->id,
                            'tipo' => 'Reemplazo',
                            'cantidad' => $cantidad,
                            'detalle' => 'Reemplazo por daño registrado al asignar nuevo producto.',
                            'estado_devolucion' => 'danado',
                            'stock_tipo_resultante' => 'danado',
                            'bodega_retorno_id' => $anterior->bodega_id,
                            'user_id' => $request->user()->id,
                        ]);
                    }
                }

                return $alertas;
            }, 3);
        } catch (\Throwable $e) {
            if ($imagenPath !== null) {
                DocumentoPrivado::descartar($imagenPath);
            }

            throw $e;
        }

        $routePrefix = $this->asignacionesRoutePrefix();
        $asignacionCreada = AsignacionInventario::with('colaborador')
            ->where('grupo_asignacion', $grupoAsignacion)
            ->oldest('id')
            ->firstOrFail();

        try {
            $notificacionService = app(NotificacionService::class);
            $urlAsignaciones = route($routePrefix . '.asignaciones.index');

            $notificacionService->notificarNuevaAsignacion(
                $asignacionCreada,
                $request->user(),
                $urlAsignaciones
            );
            $notificacionService->notificarAsignacionPendiente(
                $asignacionCreada,
                $request->user(),
                $urlAsignaciones
            );

            if ($alertasRrhhIds !== []) {
                foreach (AlertaReemplazo::whereIn('id', $alertasRrhhIds)->get() as $alertaRrhh) {
                    $notificacionService->notificarAlertaRrhh($alertaRrhh, $request->user());
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Asignaciones registradas correctamente.')
            ->with('pdf_url', route($routePrefix . '.asignaciones.pdf', $grupoAsignacion));
    }

    public function pdf($grupo)
    {
        $asignaciones = AsignacionInventario::with(['producto', 'bodega', 'colaborador', 'user.bodega', 'movimientoInicial', 'movimientos'])
            ->where('grupo_asignacion', $grupo)
            ->get();

        abort_if($asignaciones->isEmpty(), 404, 'No se encontraron asignaciones para este PDF.');
        $asignaciones->each(function (AsignacionInventario $asignacion) {
            $this->autorizarAsignacionInventario($asignacion);
            $asignacion->cantidad = $asignacion->movimientoInicial?->cantidad
                ?? ($asignacion->cantidad_asignada + $asignacion->movimientos->where('tipo', 'Devolucion')->sum('cantidad'));
        });

        $colaborador = $asignaciones->first()->colaborador;

        if (!$colaborador) {
            abort(404, 'No se encontró el colaborador de esta asignación.');
        }

        $total = $asignaciones->sum(function ($a) {
            return ($a->costo_unitario ?? 0) * $a->cantidad;
        });

        $creador = $asignaciones->first()->user;
        $asignadorNombre = $creador?->name ?? 'No identificado';

        $bodegaAsignador = $creador?->bodega?->nombre
            ?? optional($asignaciones->first()?->bodega)->nombre
            ?? 'No definida';

        return view('admin.asignaciones.pdf', compact(
            'colaborador',
            'asignaciones',
            'total',
            'asignadorNombre',
            'bodegaAsignador',
            'grupo'
        ));
    }

    public function uploadPdfFirmado(Request $request, AsignacionInventario $asignacion)
    {
        $this->autorizarAsignacionInventario($asignacion);

        if (!Schema::hasTable('asignacion_inventario_archivos')) {
            return back()->with('error', 'La tabla para evidencias no existe. Ejecuta php artisan migrate.');
        }

        $request->validate([
            'evidencias' => ['required_without:pdf_firmado', 'array', 'min:1', 'max:10'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            // Se mantiene el campo anterior para clientes o formularios aún no actualizados.
            'pdf_firmado' => ['required_without:evidencias', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'evidencias.required_without' => 'Selecciona al menos un PDF firmado o una imagen de entrega.',
            'evidencias.*.mimes' => 'Las evidencias deben ser PDF, JPG, JPEG, PNG o WEBP.',
            'evidencias.*.max' => 'Cada evidencia puede pesar como máximo 10 MB.',
        ]);

        $archivos = collect($request->file('evidencias', []));

        if ($request->hasFile('pdf_firmado')) {
            $archivos->push($request->file('pdf_firmado'));
        }

        $evidencias = [];
        try {
            foreach ($archivos as $file) {
                $esPdf = $file->getMimeType() === 'application/pdf';
                $carpeta = $esPdf ? 'documentos' : 'imagenes';
                $evidencias[] = [
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo_documento' => $esPdf ? 'asignacion_firmada' : 'evidencia_entrega',
                    'ruta' => DocumentoPrivado::guardar($file, "asignaciones/evidencias/{$asignacion->id}/{$carpeta}"),
                    'nombre_original' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'subido_por_user_id' => auth()->id(),
                ];
            }

            DB::transaction(function () use ($evidencias, $asignacion) {
                $asignacion = AsignacionInventario::whereKey($asignacion->id)->lockForUpdate()->firstOrFail();

                foreach ($evidencias as $evidencia) {
                    AsignacionInventarioArchivo::create($evidencia);
                    if ($evidencia['tipo_documento'] === 'asignacion_firmada') {
                        $asignacion->pdf_firmado = $evidencia['ruta'];
                    }
                }

                $tienePdf = $asignacion->pdfsAsignacionFirmados()->exists();
                $tieneImagen = $asignacion->imagenesEntrega()->exists();
                $asignacion->estado_evidencia = $tienePdf && $tieneImagen ? 'completa' : 'pendiente';
                $asignacion->save();
            }, 3);
        } catch (\Throwable $e) {
            foreach ($evidencias as $evidencia) {
                DocumentoPrivado::descartar($evidencia['ruta']);
            }

            throw $e;
        }

        $asignacion->refresh();
        $routePrefix = $this->asignacionesRoutePrefix();

        if ($asignacion->estado_evidencia === 'completa') {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('success', 'Asignación completa');
        }

        $faltantes = [];

        if (!$asignacion->pdfsAsignacionFirmados()->exists()) {
            $faltantes[] = '1 PDF firmado';
        }

        if (!$asignacion->imagenesEntrega()->exists()) {
            $faltantes[] = '1 imagen de entrega';
        }

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('error', 'Evidencias guardadas. La asignación sigue pendiente; falta: ' . implode(' y ', $faltantes) . '.');
    }

    public function uploadPdfDevolucionFirmado(Request $request, string $grupo)
    {
        if (!Schema::hasTable('asignacion_inventario_archivos')) {
            return back()->with('error', 'La tabla para PDFs firmados no existe. Ejecuta php artisan migrate.');
        }

        $movimientos = $this->movimientosDevolucionAutorizados($grupo);

        if ($movimientos->isEmpty()) {
            abort(404, 'No se encontraron movimientos de devolución para este grupo.');
        }

        $data = $request->validate([
            'pdf_firmado' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['pdf_firmado'];
        $path = DocumentoPrivado::guardar($file, 'asignaciones/firmados/devoluciones/' . $grupo);

        try {
            AsignacionInventarioArchivo::create([
                'asignacion_inventario_id' => $movimientos->first()->asignacion_inventario_id,
                'grupo_devolucion' => $grupo,
                'tipo_documento' => 'devolucion_firmada',
                'ruta' => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'tamano' => $file->getSize(),
                'subido_por_user_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            DocumentoPrivado::descartar($path);
            throw $e;
        }

        $routePrefix = $this->asignacionesRoutePrefix();

        return redirect()
            ->route($routePrefix . '.asignaciones.index')
            ->with('success', 'PDF firmado de devolución cargado correctamente.');
    }

    public function verPdfFirmado(AsignacionInventarioArchivo $archivo)
    {
        if ($archivo->tipo_documento === 'devolucion_firmada') {
            $movimientos = $this->movimientosDevolucionAutorizados((string) $archivo->grupo_devolucion);

            if ($movimientos->isEmpty()) {
                abort(403);
            }
        } else {
            $archivo->loadMissing('asignacion');

            if (!$archivo->asignacion) {
                abort(404);
            }

            $this->autorizarAsignacionInventario($archivo->asignacion);
        }

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    public function devolver(Request $request, AsignacionInventario $asignacion)
    {
        $this->autorizarAsignacionInventario($asignacion);
        $data = $request->validate($this->reglasDevolucion() + [
            'cantidad_devuelta' => ['required', 'integer', 'min:1'],
        ]);

        return $this->procesarDevoluciones($request, $data, AsignacionInventario::whereKey($asignacion->id),
            fn () => (int) $data['cantidad_devuelta'], 'individual');
    }

    public function devolverLote(Request $request)
    {
        $data = $request->validate($this->reglasDevolucion() + [
            'seleccionadas' => ['required', 'array', 'min:1', 'max:100'],
            'seleccionadas.*' => ['required', 'integer', 'distinct', 'exists:asignaciones_inventarios,id'],
            'devoluciones' => ['required', 'array', 'min:1'],
            'devoluciones.*' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->procesarDevoluciones($request, $data, AsignacionInventario::whereKey($data['seleccionadas']),
            fn ($asignacion) => (int) ($data['devoluciones'][$asignacion->id] ?? 0), 'lote');
    }

    public function devolverTodoColaborador(Request $request, string $colaboradorCodigo)
    {
        $data = $request->validate($this->reglasDevolucion());
        $query = AsignacionInventario::where('colaborador_codigo', $colaboradorCodigo)->where('estado', 'Activa');
        if ((int) $request->user()->role_id !== 1) {
            $query->where('user_id', $request->user()->id);
        }

        return $this->procesarDevoluciones($request, $data, $query, fn ($asignacion) => $asignacion->cantidad_asignada, 'total');
    }

    private function reglasDevolucion(): array
    {
        return [
            'solicitud_id' => ['nullable', 'uuid'],
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
            'estado_devolucion' => ['nullable', 'in:buen_estado,danado,perdido,baja'],
        ];
    }

    private function procesarDevoluciones(Request $request, array $data, $query, callable $cantidadPedida, string $modalidad)
    {
        $grupo = (string) Str::uuid();
        $estado = $data['estado_devolucion'] ?? 'buen_estado';
        $detalle = $data['detalle_devolucion'] ?? 'Devolución de producto.';

        $ids = DB::transaction(function () use ($data, $query, $cantidadPedida, $modalidad, $grupo, $estado, $detalle, $request) {
            SolicitudInventarioService::registrar('devolucion_' . $modalidad, $data['solicitud_id'] ?? null);
            $procesadas = [];
            $asignaciones = (clone $query)->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get();

            foreach ($asignaciones as $asignacion) {
                $this->autorizarAsignacionInventario($asignacion);
                abort_unless($this->bodegaAccess->canReceiveStock($request->user(), (int) $asignacion->bodega_id), 403);
                $cantidad = $cantidadPedida($asignacion);
                if ($cantidad === 0) {
                    continue;
                }

                if ($asignacion->estado !== 'Activa' || $cantidad > $asignacion->cantidad_asignada) {
                    throw ValidationException::withMessages(['cantidad_devuelta' => 'La devolución supera el saldo activo o ya se procesó.']);
                }

                $this->stock->devolver($asignacion, $cantidad, $estado, $detalle);
                $asignacion->cantidad_asignada -= $cantidad;
                if ($asignacion->cantidad_asignada === 0) {
                    $asignacion->estado = 'Devuelta';
                }
                $asignacion->save();
                AsignacionMovimiento::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo' => 'Devolucion',
                    'cantidad' => $cantidad,
                    'detalle' => $detalle,
                    'grupo_devolucion' => $grupo,
                    'estado_devolucion' => $estado,
                    'stock_tipo_resultante' => $estado === 'buen_estado' ? 'usado' : $estado,
                    'bodega_retorno_id' => $asignacion->bodega_id,
                    'user_id' => $request->user()->id,
                ]);
                $procesadas[] = $asignacion->id;
            }

            if ($procesadas === []) {
                throw ValidationException::withMessages(['cantidad_devuelta' => 'No hay asignaciones activas con una cantidad válida.']);
            }

            return $procesadas;
        }, 3);

        $ruta = $this->asignacionesRoutePrefix() . '.asignaciones.index';
        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                AsignacionInventario::whereKey($ids)->get(), $request->user(), 'una devolución'
            )
        );

        return redirect()->route($ruta)
            ->with('success', 'Devolución registrada correctamente.')
            ->with('grupo_devolucion', $grupo);
    }

    public function hojaDevolucion(string $grupo)
    {
        $movimientos = $this->movimientosDevolucionAutorizados($grupo);

        if ($movimientos->isEmpty()) {
            abort(404, 'No se encontraron movimientos de devolución para este grupo.');
        }

        $primerMovimiento = $movimientos->first();

        return view('admin.asignaciones.hoja_devolucion', compact('movimientos', 'grupo', 'primerMovimiento'));
    }

    private function autorizarAsignacionInventario(AsignacionInventario $asignacion): void
    {
        $usuario = auth()->user();

        if ((int) $usuario->role_id === 1) {
            return;
        }

        if ((int) $usuario->role_id === 2 && (int) $asignacion->user_id === (int) $usuario->id) {
            return;
        }

        if (
            (int) $usuario->role_id === 3
            && $usuario->almacenistasAsignados()->whereKey($asignacion->user_id)->exists()
        ) {
            return;
        }

        abort(403);
    }

    private function asignacionesRoutePrefix(): string
    {
        return match ((int) auth()->user()->role_id) {
            2 => 'operador',
            3 => 'supervisor',
            default => 'admin',
        };
    }

    private function movimientosDevolucionAutorizados(string $grupo)
    {
        $query = AsignacionMovimiento::with([
            'asignacion',
            'asignacion.colaborador',
            'asignacion.producto',
            'asignacion.bodega',
            'user',
        ])
            ->where('tipo', 'Devolucion')
            ->where('grupo_devolucion', $grupo)
            ->orderBy('created_at');

        if ((int) auth()->user()->role_id !== 1) {
            $query->where('user_id', auth()->id());
        }

        return $query->get();
    }

    private function reemplazoAutorizado(Request $request, string $colaborador, array $item): ?AsignacionInventario
    {
        if (empty($item['es_reemplazo'])) {
            return null;
        }

        $anterior = AsignacionInventario::whereKey($item['asignacion_anterior_id'] ?? 0)
            ->where('colaborador_codigo', $colaborador)
            ->where('producto_codigo', $item['producto_codigo'])
            ->where('bodega_id', $item['bodega_id'])
            ->where('estado', 'Activa')
            ->when((int) $request->user()->role_id === 2, fn ($query) => $query->where('user_id', $request->user()->id))
            ->lockForUpdate()->first();

        if (!$anterior) {
            throw ValidationException::withMessages(['items' => 'Selecciona una asignación anterior activa de este colaborador y bodega.']);
        }

        return $anterior;
    }
}
