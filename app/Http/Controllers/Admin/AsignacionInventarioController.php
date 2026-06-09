<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Colaborador;
use App\Models\Bodega;
use App\Models\AsignacionMovimiento;
use App\Models\AsignacionInventarioArchivo;
use App\Models\AlertaReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AsignacionVidaUtilService;
use App\Services\NotificacionService;
use Carbon\Carbon;
use App\Models\Role;

class AsignacionInventarioController extends Controller
{
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
        $inventariosQuery = Inventario::with('producto', 'bodega');
        $bodegasQuery = Bodega::query();

        if ($this->requiereRestriccionBodega()) {
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

        $imagenPath = null;

        if ($request->hasFile('imagen')) {
            $imagenPath = $request->file('imagen')->store('asignaciones', 'public');
        }

        $items = collect($data['items'])->values();

        $solicitudesAgrupadas = $items
            ->groupBy(fn ($item) => $item['producto_codigo'] . '|' . $item['bodega_id'])
            ->map(fn ($grupo) => [
                'producto_codigo' => $grupo->first()['producto_codigo'],
                'bodega_id' => (int) $grupo->first()['bodega_id'],
                'cantidad_total' => (int) $grupo->sum('cantidad_asignada'),
            ]);

        foreach ($solicitudesAgrupadas as $solicitud) {
            if ($this->requiereRestriccionBodega() && (int) $solicitud['bodega_id'] !== (int) auth()->user()->bodega_id) {
                return back()->withInput()->with('error', 'No tienes permiso para asignar productos de otra bodega.');
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
                ->latest('fecha')
                ->first();

            if (!$asignacionActiva) {
                return back()
                    ->withInput()
                    ->with('error', 'Marcaste reemplazo por daño pero no existe una asignación activa previa para el producto seleccionado.');
            }
        }

        $grupoAsignacion = (string) Str::uuid();
        $alertasRrhhIds = [];

        DB::transaction(function () use ($data, $items, $imagenPath, $grupoAsignacion, &$alertasRrhhIds) {
            $vidaUtilService = app(AsignacionVidaUtilService::class);

            foreach ($items as $item) {
                $inventario = Inventario::with('producto')
                    ->where('producto_codigo', $item['producto_codigo'])
                    ->where('bodega_id', $item['bodega_id'])
                    ->firstOrFail();

                $inventario->decrement('cantidad', (int) $item['cantidad_asignada']);

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
                ];

                if (!empty($inventario->producto?->vida_util_meses)) {
                    $payload['fecha_vencimiento'] = Carbon::parse($data['fecha'])
                        ->addMonths($inventario->producto->vida_util_meses);
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
                        ->latest('fecha')
                        ->first();

                    if ($anterior) {
                        $fechaDanio = !empty($item['fecha_dano'])
                            ? Carbon::parse($item['fecha_dano'])
                            : Carbon::parse($data['fecha']);

                        $alertaRrhh = $vidaUtilService->procesarReemplazoPorDanio($anterior, $asignacion, $fechaDanio);

                        if ($alertaRrhh) {
                            $alertasRrhhIds[] = $alertaRrhh->id;
                        }

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
        $asignaciones = AsignacionInventario::with(['producto', 'bodega', 'colaborador', 'user.bodega'])
            ->where('grupo_asignacion', $grupo)
            ->where('cantidad_asignada', '>', 0)
            ->get();

        if ($asignaciones->isEmpty()) {
            abort(404, 'No se encontraron asignaciones para este PDF.');
        }

        $asignaciones->each(fn (AsignacionInventario $asignacion) => $this->autorizarAsignacionInventario($asignacion));

        $colaborador = $asignaciones->first()->colaborador;

        if (!$colaborador) {
            abort(404, 'No se encontró el colaborador de esta asignación.');
        }

        $total = $asignaciones->sum(function ($a) {
            return ($a->costo_unitario ?? 0) * $a->cantidad_asignada;
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
            'evidencias' => ['required_without:pdf_firmado', 'array', 'min:1'],
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

        DB::transaction(function () use ($archivos, $asignacion) {
            foreach ($archivos as $file) {
                $esPdf = strtolower($file->getClientOriginalExtension()) === 'pdf'
                    || $file->getMimeType() === 'application/pdf';
                $tipoDocumento = $esPdf ? 'asignacion_firmada' : 'evidencia_entrega';
                $carpeta = $esPdf ? 'documentos' : 'imagenes';
                $path = $file->store("asignaciones/evidencias/{$asignacion->id}/{$carpeta}", 'public');

                AsignacionInventarioArchivo::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo_documento' => $tipoDocumento,
                    'ruta' => $path,
                    'nombre_original' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'subido_por_user_id' => auth()->id(),
                ]);

                if ($esPdf) {
                    $asignacion->pdf_firmado = $path;
                }
            }

            $tienePdf = $asignacion->pdfsAsignacionFirmados()->exists();
            $tieneImagen = $asignacion->imagenesEntrega()->exists();
            $asignacion->estado_evidencia = $tienePdf && $tieneImagen ? 'completa' : 'pendiente';
            $asignacion->save();
        });

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

        $archivoAnterior = AsignacionInventarioArchivo::where('grupo_devolucion', $grupo)
            ->where('tipo_documento', 'devolucion_firmada')
            ->latest('id')
            ->first();

        if ($archivoAnterior && Storage::disk('public')->exists($archivoAnterior->ruta)) {
            Storage::disk('public')->delete($archivoAnterior->ruta);
        }

        if ($archivoAnterior) {
            $archivoAnterior->delete();
        }

        $file = $data['pdf_firmado'];
        $path = $file->store('asignaciones/firmados/devoluciones/' . $grupo, 'public');

        AsignacionInventarioArchivo::create([
            'asignacion_inventario_id' => optional($movimientos->first())->asignacion_inventario_id,
            'grupo_devolucion' => $grupo,
            'tipo_documento' => 'devolucion_firmada',
            'ruta' => $path,
            'nombre_original' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'tamano' => $file->getSize(),
            'subido_por_user_id' => auth()->id(),
        ]);

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

        if (!Storage::disk('public')->exists($archivo->ruta)) {
            abort(404, 'El archivo firmado no existe.');
        }

        return response()->file(
            Storage::disk('public')->path($archivo->ruta),
            ['Content-Type' => $archivo->mime ?? 'application/pdf']
        );
    }

    public function devolver(Request $request, AsignacionInventario $asignacion)
    {
        $routePrefix = $this->asignacionesRoutePrefix();

        if ((int) auth()->user()->role_id !== 1 && (int) $asignacion->user_id !== (int) auth()->id()) {
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

        $cantidadDevuelta = min((int) $data['cantidad_devuelta'], (int) $asignacion->cantidad_asignada);

        $grupoDevolucion = (string) Str::uuid();

        DB::transaction(function () use ($asignacion, $cantidadDevuelta, $data, $grupoDevolucion) {
            $asignacion->cantidad_asignada = (int) $asignacion->cantidad_asignada - $cantidadDevuelta;

            if ((int) $asignacion->cantidad_asignada <= 0) {
                $asignacion->cantidad_asignada = 0;
                $asignacion->estado = 'Devuelta';
            }

            $asignacion->save();

            Inventario::query()
                ->where('bodega_id', $asignacion->bodega_id)
                ->where('producto_codigo', $asignacion->producto_codigo)
                ->increment('cantidad', $cantidadDevuelta);

            if (Schema::hasTable('asignacion_movimientos')) {
                AsignacionMovimiento::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo' => 'Devolucion',
                    'cantidad' => $cantidadDevuelta,
                    'detalle' => $data['detalle_devolucion'] ?? 'Devolución de producto.',
                    'grupo_devolucion' => $grupoDevolucion,
                    'user_id' => auth()->id(),
                ]);
            }
        });

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                [$asignacion->fresh()],
                $request->user(),
                'una devolución',
                route($routePrefix . '.asignaciones.index')
            )
        );

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Devolución registrada correctamente.')
            ->with('grupo_devolucion', $grupoDevolucion);
    }

    public function devolverLote(Request $request)
    {
        $routePrefix = $this->asignacionesRoutePrefix();

        $data = $request->validate([
            'seleccionadas' => ['required', 'array', 'min:1'],
            'seleccionadas.*' => ['required', 'integer', 'exists:asignaciones_inventarios,id'],
            'devoluciones' => ['required', 'array', 'min:1'],
            'devoluciones.*' => ['nullable', 'integer', 'min:1'],
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = collect($data['seleccionadas'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $asignaciones = AsignacionInventario::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($asignaciones->isEmpty()) {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'No se encontraron asignaciones para devolver.');
        }

        $totalProcesadas = 0;
        $procesadasIds = [];
        $grupoDevolucion = (string) Str::uuid();

        DB::transaction(function () use ($data, $ids, $asignaciones, &$totalProcesadas, &$procesadasIds, $grupoDevolucion) {
            foreach ($ids as $id) {
                $asignacion = $asignaciones->get((int) $id);

                if (!$asignacion || $asignacion->estado !== 'Activa') {
                    continue;
                }

                if (
                    (int) auth()->user()->role_id !== 1
                    && Schema::hasColumn('asignaciones_inventarios', 'user_id')
                    && (int) $asignacion->user_id !== (int) auth()->id()
                ) {
                    continue;
                }

                $cantidadSolicitada = (int) ($data['devoluciones'][$id] ?? 0);

                if ($cantidadSolicitada <= 0) {
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

                Inventario::query()
                    ->where('bodega_id', $asignacion->bodega_id)
                    ->where('producto_codigo', $asignacion->producto_codigo)
                    ->increment('cantidad', $cantidadDevuelta);

                if (Schema::hasTable('asignacion_movimientos')) {
                    AsignacionMovimiento::create([
                        'asignacion_inventario_id' => $asignacion->id,
                        'tipo' => 'Devolucion',
                        'cantidad' => $cantidadDevuelta,
                        'detalle' => $data['detalle_devolucion'] ?? 'Devolución múltiple.',
                        'grupo_devolucion' => $grupoDevolucion,
                        'user_id' => auth()->id(),
                    ]);
                }

                $totalProcesadas++;
                $procesadasIds[] = $asignacion->id;
            }
        });

        if ($totalProcesadas <= 0) {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'No se devolvió ninguna asignación. Verifica que estén activas y con cantidad válida.');
        }

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                AsignacionInventario::whereIn('id', $procesadasIds)->get(),
                $request->user(),
                'una devolución múltiple',
                route($routePrefix . '.asignaciones.index')
            )
        );

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Devolución múltiple registrada correctamente. Asignaciones devueltas: ' . $totalProcesadas . '.')
            ->with('grupo_devolucion', $grupoDevolucion);
    }

    public function devolverTodoColaborador(Request $request, string $colaboradorCodigo)
    {
        $routePrefix = $this->asignacionesRoutePrefix();

        $data = $request->validate([
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = AsignacionInventario::query()
            ->where('colaborador_codigo', $colaboradorCodigo)
            ->where('estado', 'Activa');

        if ((int) auth()->user()->role_id !== 1 && Schema::hasColumn('asignaciones_inventarios', 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        $asignaciones = $query->get();

        if ($asignaciones->isEmpty()) {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'No hay asignaciones activas para devolver.');
        }

        $grupoDevolucion = (string) Str::uuid();

        DB::transaction(function () use ($asignaciones, $data, $grupoDevolucion) {
            foreach ($asignaciones as $asignacion) {
                $cantidadDevuelta = (int) $asignacion->cantidad_asignada;

                if ($cantidadDevuelta <= 0) {
                    continue;
                }

                $asignacion->cantidad_asignada = 0;
                $asignacion->estado = 'Devuelta';
                $asignacion->save();

                Inventario::query()
                    ->where('bodega_id', $asignacion->bodega_id)
                    ->where('producto_codigo', $asignacion->producto_codigo)
                    ->increment('cantidad', $cantidadDevuelta);

                if (Schema::hasTable('asignacion_movimientos')) {
                    AsignacionMovimiento::create([
                        'asignacion_inventario_id' => $asignacion->id,
                        'tipo' => 'Devolucion',
                        'cantidad' => $cantidadDevuelta,
                        'detalle' => $data['detalle_devolucion'] ?? 'Devolución total por colaborador.',
                        'grupo_devolucion' => $grupoDevolucion,
                        'user_id' => auth()->id(),
                    ]);
                }
            }
        });

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                $asignaciones->map->fresh(),
                $request->user(),
                'una devolución total',
                route($routePrefix . '.asignaciones.index')
            )
        );

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Se devolvió todo el inventario activo del colaborador.')
            ->with('grupo_devolucion', $grupoDevolucion);
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

    private function requiereRestriccionBodega(): bool
    {
        $usuario = auth()->user();

        if ((int) $usuario->role_id === 1) {
            return false;
        }

        $nombreRol = strtolower(trim((string) optional(Role::find($usuario->role_id))->nombre));

        return in_array($nombreRol, ['almacenista', 'encargado', 'supervisor', 'coordinador', 'operador'], true)
            || in_array((int) $usuario->role_id, [2, 3], true);
    }
}
