<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Auditoria;
use App\Sistema\ConsultaCampos;
use App\Sistema\Permisos;
use App\Sistema\Presentacion;
use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Colaborador;
use App\Models\EntregaInventario;
use App\Models\IncidenciaInventario;
use App\Models\User;
use App\Models\Bodega;
use App\Models\AsignacionMovimiento;
use App\Models\AsignacionInventarioArchivo;
use App\Models\AlertaReemplazo;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AccesoCustodia;
use App\Services\AsignacionVidaUtilService;
use App\Services\RegistrarAsignaciones;
use App\Services\RequerimientosAsignacion;
use App\Models\MotivoAsignacion;
use App\Services\NotificacionService;
use Carbon\Carbon;
use App\Services\ResolverCustodia;
use App\Services\AccesoInventarioOperativo;
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use App\Services\SolicitudInventarioService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignacionInventarioController extends Controller
{
    public function __construct(
        private AccesoInventarioOperativo $inventario,
        private InventarioStockService $stock,
        private AccesoCustodia $acceso,
        private ResolverCustodia $resolverCustodia,
    ) {}

    public function index(Request $request)
    {
        $usuario = auth()->user();
        $routePrefix = $this->asignacionesRoutePrefix();
        $permisos = app(Permisos::class);
        $puedeCrearAsignaciones = $permisos->ruta($usuario, $routePrefix.'.asignaciones.create');
        $puedeDevolverLote = $permisos->ruta($usuario, $routePrefix.'.asignaciones.devolver_lote');
        $puedeDevolverTodo = $permisos->ruta($usuario, $routePrefix.'.asignaciones.devolver_todo_colaborador');
        $puedeVerMovimientos = $puedeDevolverLote || $puedeCrearAsignaciones || $this->inventario->tiene($usuario, 'asignaciones.datos_exportar');

        $relaciones = ['colaborador', 'producto', 'bodega', 'user', 'entrega.documentos', 'incidencias'];

        if (Schema::hasTable('asignacion_inventario_archivos')) {
            $relaciones[] = 'evidencias.subidoPor';
        }

        if (config('inventario.seguimiento_unidades')) {
            $relaciones[] = 'periodosActivos';
        }
        $query = AsignacionInventario::with($relaciones)->latest();

        $permiso = $request->boolean('xlsx') ? 'asignaciones.datos_exportar' : 'asignaciones.consultar';
        abort_unless($this->inventario->tiene($usuario, $permiso), 403);
        $this->inventario->limitarAsignaciones($query, $usuario, $permiso);

        $seguimiento = $request->query('seguimiento');
        if ($seguimiento === 'documentacion') {
            $query->whereHas('entrega', fn ($q) => $q->whereDoesntHave('documentos',
                fn ($documentos) => $documentos->where('tipo_documento', 'asignacion_firmada')));
        }
        app(ConsultaCampos::class)->filtrar($query, $request, 'asignaciones');
        $puedeDevolverTodo = $puedeDevolverTodo && !$seguimiento && !collect($request->input('campo'))->contains(fn ($valor) => filled($valor));
        if ($request->boolean('xlsx')) {
            $exportar = app(ExportacionesOperativas::class);
            $this->inventario->alcances($usuario, $permiso)->each(fn ($alcance) => $this->inventario->registrar($alcance, $permiso, 'asignaciones'));
            if ($request->boolean('devoluciones')) {
                abort_unless($puedeVerMovimientos, 403);

                return $exportar->devoluciones($query);
            }

            return $exportar->asignaciones($query);
        }
        $asignaciones = $query->get();
        $tablaCampos = app(Presentacion::class)->operativa('asignaciones', $usuario, $asignaciones->pluck('id'));
        $vida = app(AsignacionVidaUtilService::class);
        $corte = now();
        $vigencias = $asignaciones->where('estado', 'Activa')
            ->mapWithKeys(fn ($fila) => [$fila->id => $vida->restante($fila, $corte)]);

        $asignacionesPorColaborador = $asignaciones
            ->groupBy('colaborador_codigo')
            ->map(function ($items) use ($usuario, $puedeDevolverLote, $puedeDevolverTodo) {
                $ordenadas = $items->sortByDesc('fecha')->values();
                $colaborador = optional($ordenadas->first())->colaborador;

                $codigo = $ordenadas->first()->colaborador_codigo;
                $devolvibles = $ordenadas->filter(fn ($a) => $a->estado === 'Activa' && $a->cantidad_asignada > 0
                    && $this->acceso->puedeResolver($usuario, $a))->pluck('id');
                $todo = AsignacionInventario::custodiaAbierta()->where('colaborador_codigo', $codigo);
                if ($puedeDevolverTodo) {
                    $this->inventario->custodiasParaResolverTodo($todo, $usuario);
                }
                return [
                    'colaborador_codigo' => $codigo,
                    'puede_devolver_todo' => $puedeDevolverTodo && $devolvibles->isNotEmpty()
                        && $this->inventario->buscarCustodias($usuario, 'asignaciones.devolver', $todo->get()) !== null,
                    'devolvibles_lote' => $puedeDevolverLote ? $devolvibles : collect(),
                    'colaborador_nombre' => $colaborador->nombre ?? 'Sin nombre',
                    'asignaciones' => $ordenadas,
                    'total_activo' => $ordenadas->where('estado', 'Activa')->whereNull('sustituida_por_id')->sum('cantidad_asignada'),
                    'sin_retorno' => $ordenadas->where('estado', 'Activa')->whereNotNull('sustituida_por_id')->sum('cantidad_asignada'),
                ];
            })
            ->values();

        $movimientos = collect();

        if (Schema::hasTable('asignacion_movimientos')) {
            $movimientosQuery = AsignacionMovimiento::with(['asignacion.colaborador', 'user'])
                ->latest()
                ->limit(30);

            $movimientosQuery->whereHas('asignacion', fn ($q) => $this->inventario->limitarAsignaciones($q, $usuario, 'asignaciones.consultar'));

            $movimientos = $movimientosQuery->get();
        }

        $pdfsDevolucionFirmados = collect();

        if (Schema::hasTable('asignacion_inventario_archivos') && $movimientos->isNotEmpty()) {
            $gruposDevolucion = $movimientos
                ->whereIn('tipo', ['Devolucion', 'Reemplazo'])
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
            'puedeVerMovimientos',
            'vigencias',
            'tablaCampos'
        ));
    }

    public function create(Request $request, RequerimientosAsignacion $requerimientos)
    {
        $inventariosQuery = Inventario::with('producto', 'bodega')
            ->whereIn('stock_tipo', ['nuevo', 'usado'])->where('cantidad', '>', 0);
        $bodegasQuery = Bodega::query();

        $usuario = $request->user();
        $alcances = $this->inventario->alcances($usuario, 'asignaciones.crear');
        $ids = $this->inventario->bodegasPara($usuario, 'asignaciones.crear');
        $inventarios = $inventariosQuery->whereIn('bodega_id', $ids)->get();
        $colaboradores = Colaborador::where('estado', 'Activo')
            ->when(!$alcances->contains(fn ($alcance) => $alcance->regiones === null),
                fn ($q) => $q->whereIn('region_id', $alcances->flatMap(fn ($alcance) => $alcance->regiones)->unique()->all()))
            ->get();
        $bodegas = $bodegasQuery->whereKey($ids)->get();
        $motivos = MotivoAsignacion::where('activo', true)->orderBy('id')->get();
        $consolidadosOrigen = $requerimientos->disponibles($request->user())->latest('id')->get(['id', 'nombre']);

        return view('admin.asignaciones.create', compact(
            'inventarios',
            'colaboradores',
            'bodegas', 'motivos', 'consolidadosOrigen'
        ));
    }

    public function contexto(Request $request, RequerimientosAsignacion $requerimientos)
    {
        $datos = $request->validate([
            'colaborador_codigo' => ['required', 'string', Rule::exists('colaboradores', 'codigo')->where('estado', 'Activo')],
            'consolidado_id' => ['nullable', 'integer'],
        ]);
        $usuario = $request->user();
        $colaborador = Colaborador::findOrFail($datos['colaborador_codigo']);
        $bodegas = $this->inventario->bodegasPara($usuario, 'asignaciones.crear', [$colaborador->region_id]);
        abort_if($bodegas === [], 403);
        $consulta = AsignacionInventario::custodiaActiva()->where('colaborador_codigo', $datos['colaborador_codigo']);
        $this->inventario->limitarAsignaciones($consulta, $usuario, 'asignaciones.consultar', [$colaborador->region_id]);
        $custodias = $consulta->orderByDesc('id')->get(['id', 'producto_codigo', 'fecha', 'cantidad_asignada']);
        $pendientes = isset($datos['consolidado_id'])
            ? $requerimientos->pendientes($requerimientos->verificar($usuario, $datos['consolidado_id']), $datos['colaborador_codigo'], $usuario)
            : collect();
        $stock = $pendientes->isEmpty() ? collect() : $requerimientos->cantidadesDisponibles(
            $bodegas, $pendientes->pluck('producto_codigo')->all());
        $productos = $pendientes->map(fn ($fila) => [
            'codigo' => $fila->producto_codigo, 'nombre' => $fila->producto?->nombre ?? $fila->producto_codigo,
            'aprobada' => $fila->cantidad, 'entregada' => $fila->asignaciones_count, 'pendiente' => $fila->cantidad - $fila->asignaciones_count,
            'existencias' => $stock->get($fila->producto_codigo, collect())->mapWithKeys(fn ($saldo) => [$saldo->bodega_id => (int) $saldo->total]),
        ])->values();

        return response()->json(compact('custodias', 'productos', 'bodegas'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, ValoresCampos $campos, RegistrarAsignaciones $registro)
    {
        $data = $request->validate([
            'solicitud_id' => ['required', 'uuid'],
            'consolidado_id' => ['nullable', 'integer'],
            'colaborador_codigo' => ['required', 'string', Rule::exists('colaboradores', 'codigo')->where('estado', 'Activo')],
            'fecha' => ['nullable', 'date', 'before_or_equal:now'],
            'modalidad' => ['nullable', 'in:directa,tercero'],
            'tercero' => ['nullable', 'string', 'max:180'],
            'confirmar_recepcion' => ['nullable', 'boolean'],
            'recibida_en' => ['nullable', 'date', 'before_or_equal:now',
                Rule::prohibitedIf(fn () => !$request->boolean('confirmar_recepcion'))],
            'medio_confirmacion' => [Rule::requiredIf(fn () => $request->boolean('confirmar_recepcion')),
                'nullable', Rule::in(array_keys(EntregaInventario::MEDIOS))],
            'costo_unitario' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'aprobado_por' => ['nullable', 'string', 'max:255'],
            'medio_solicitud' => ['nullable', Rule::in(['WhatsApp', 'Correo', RegistrarAsignaciones::MEDIO_INTERNO])],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array'],
            'items.*.producto_codigo' => ['required', 'string', 'distinct:ignore_case', 'exists:productos,codigo'],
            'items.*.bodega_id' => ['required', 'integer', 'exists:bodegas,id'],
            'items.*.cantidad_asignada' => ['sometimes', 'required', 'integer', 'min:1',
                $request->filled('consolidado_id') ? 'max:'.RegistrarAsignaciones::MAX_UNIDADES : 'in:1'],
            'items.*.motivo_inventario_nuevo' => ['nullable', 'string', 'max:500'],
            'items.*.motivo_asignacion' => ['nullable', 'string', 'max:45'],
            'items.*.sustituir_asignacion_id' => ['nullable', 'integer', 'min:1'],
            'items.*.resoluciones' => ['missing'],
        ], [
            'items.*.cantidad_asignada.in' => 'Solo se puede asignar una unidad por código al colaborador.',
            'items.*.producto_codigo.distinct' => 'Incluye cada código de producto una sola vez.',
        ]);

        app(Presentacion::class)->validar($request, 'asignaciones', true);
        $valores = $campos->preparar($request, 'asignaciones', true);
        $usuario = $request->user();
        $region = Colaborador::whereKey($data['colaborador_codigo'])->value('region_id');
        $this->inventario->autorizar($usuario, 'asignaciones.crear', array_map('intval', array_column($data['items'], 'bodega_id')), [$region]);
        $items = collect($data['items'])->map(function ($item) {
            $item['producto_codigo'] = Producto::findOrFail($item['producto_codigo'])->codigo;

            return $item;
        })->sortBy('producto_codigo');

        if ($items->pluck('producto_codigo')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'Incluye cada código de producto una sola vez.']);
        }

        $foto = $request->file('imagen');
        $rutaFoto = $foto ? DocumentoPrivado::guardar($foto, 'asignaciones/entregas') : null;
        $adjuntar = $rutaFoto ? fn ($entrega, $primera) => $entrega->documentos()->create(
            $this->datosArchivo($foto, $rutaFoto, $primera, 'evidencia_entrega')
        ) : null;
        try {
            $entrega = DB::transaction(fn () => $registro->registrar($usuario, $data, $items, $valores, $adjuntar), 3);
        } catch (\Throwable $e) {
            if ($rutaFoto !== null) {
                DocumentoPrivado::descartar($rutaFoto);
            }

            throw $e;
        }

        $prefijo = $this->asignacionesRoutePrefix();
        $destino = app(Permisos::class)->ruta($usuario, $prefijo.'.asignaciones.entrega')
            ? route($prefijo.'.asignaciones.entrega', $entrega) : route($prefijo.'.asignaciones.create');

        return redirect()->to($destino)->with('success', 'Salida registrada correctamente.');
    }

    private function notificarAlertas(array $alertas, User $usuario): void
    {
        foreach (AlertaReemplazo::whereKey($alertas)->get() as $alerta) {
            app(NotificacionService::class)->safeAction(fn ($servicio) => $servicio->notificarAlertaRrhh($alerta, $usuario));
        }
    }

    public function entrega(string $grupo)
    {
        $asignaciones = $this->filasDelGrupo($grupo)->load('producto', 'colaborador', 'bodega', 'incidencias',
            'evidencias.subidoPor', 'entrega', 'requerimiento.participante.consolidado');
        if (config('inventario.seguimiento_unidades')) {
            $asignaciones->load('periodosActivos');
        }
        $entrega = EntregaInventario::with('confirmador', 'documentos.subidoPor')->find($grupo);
        $archivos = $entrega?->documentos ?? $asignaciones->flatMap->evidencias;
        $routePrefix = $this->asignacionesRoutePrefix();
        $puedeConfirmar = $entrega && $this->acceso->puedeConfirmar(auth()->user(), $asignaciones);
        $vida = app(AsignacionVidaUtilService::class);
        $vigencias = $asignaciones->mapWithKeys(fn ($fila) => [$fila->id => $vida->restante($fila, now())]);

        return view('admin.asignaciones.entrega', compact(
            'grupo', 'asignaciones', 'entrega', 'archivos', 'routePrefix', 'puedeConfirmar', 'vigencias'
        ));
    }

    public function confirmarRecepcion(Request $request, string $grupo, AsignacionVidaUtilService $vida)
    {
        $datos = $request->validate([
            'medio_confirmacion' => ['nullable', Rule::in(array_keys(EntregaInventario::MEDIOS))],
            'recibida_en' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        DB::transaction(function () use ($grupo, $request, $datos, $vida) {
            $entrega = EntregaInventario::whereKey($grupo)->lockForUpdate()->firstOrFail();
            $filas = $this->filasDelGrupo($grupo, 'asignaciones.confirmar_recepcion', bloquear: true);
            $fecha = empty($datos['recibida_en']) ? null : Carbon::parse($datos['recibida_en']);
            if ($fecha && $fecha->lt($entrega->salida_en)) {
                throw ValidationException::withMessages(['recibida_en' => 'La recepción no puede ser anterior a la salida.']);
            }
            if ($entrega->confirmada_en !== null && ($entrega->recibida_en !== null || $fecha === null)) {
                throw ValidationException::withMessages(['recibida_en' => 'La recepción ya fue registrada.']);
            }

            if ($entrega->confirmada_en === null) {
                if (empty($datos['medio_confirmacion'])) {
                    throw ValidationException::withMessages(['medio_confirmacion' => 'Selecciona un medio de confirmación.']);
                }
                $entrega->fill([
                    'medio_confirmacion' => $datos['medio_confirmacion'], 'confirmada_por' => $request->user()->id,
                    'confirmada_en' => now(),
                ]);
            }
            $entrega->recibida_en = $fecha;
            $entrega->save();
            if ($fecha !== null) {
                AsignacionInventario::custodiaAbierta()->where('grupo_asignacion', $grupo)
                    ->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get()
                    ->each(fn ($asignacion) => $vida->registrarInicio($asignacion, $fecha));
            }
            app(Auditoria::class)->registrar('asignaciones', 'confirmar_recepcion', $grupo, null,
                ['recibida_en' => $fecha?->toDateTimeString(), 'medio' => $entrega->medio_confirmacion]);
        }, 3);

        return $this->volverAEntrega($grupo, 'Recepción registrada.');
    }

    public function subirDocumentoEntrega(Request $request, string $grupo)
    {
        $filas = $this->filasDelGrupo($grupo, 'asignaciones.evidencias');
        $entrega = EntregaInventario::findOrFail($grupo);
        $request->validate([
            'pdf_firmado' => ['required_without:foto', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
            'foto' => ['required_without:pdf_firmado', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $archivos = [];
        try {
            foreach (['pdf_firmado' => 'asignacion_firmada', 'foto' => 'evidencia_entrega'] as $campo => $tipo) {
                if ($file = $request->file($campo)) {
                    $archivos[] = $this->datosArchivo($file, DocumentoPrivado::guardar($file, 'asignaciones/entregas'),
                        $filas->first()->id, $tipo);
                }
            }
            DB::transaction(function () use ($entrega, $archivos) {
                $bloqueada = EntregaInventario::whereKey($entrega->id)->lockForUpdate()->firstOrFail();
                $this->filasDelGrupo($bloqueada->id, 'asignaciones.evidencias', bloquear: true);
                $bloqueada->documentos()->createMany($archivos);
                app(Auditoria::class)->registrar('asignaciones', 'documentar_entrega', $bloqueada->id, null,
                    ['documentos' => array_column($archivos, 'tipo_documento')]);
            }, 3);
        } catch (\Throwable $e) {
            foreach ($archivos as $archivo) {
                DocumentoPrivado::descartar($archivo['ruta']);
            }
            throw $e;
        }

        return $this->volverAEntrega($grupo, 'Respaldo guardado.');
    }

    public function verIncidencia(IncidenciaInventario $incidencia)
    {
        $this->autorizarAsignacionInventario($incidencia->asignacion);
        $incidencia->load('registrador', 'asignacion.producto', 'asignacion.colaborador', 'asignacion.bodega');
        $routePrefix = $this->asignacionesRoutePrefix();

        return view('admin.asignaciones.incidencia', compact('incidencia', 'routePrefix'));
    }

    public function subirArchivoIncidencia(Request $request, IncidenciaInventario $incidencia)
    {
        $this->autorizarAsignacionInventario($incidencia->asignacion, 'asignaciones.evidencias');
        $archivo = $request->validate(['evidencia' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']])['evidencia'];
        $path = DocumentoPrivado::guardar($archivo, 'asignaciones/incidencias');

        try {
            DB::transaction(function () use ($incidencia, $archivo, $path) {
                $incidencia = IncidenciaInventario::whereKey($incidencia->id)->lockForUpdate()->firstOrFail();
                $this->autorizarAsignacionInventario($incidencia->asignacion, 'asignaciones.evidencias', bloquear: true);
                if ($incidencia->archivo !== null) {
                    throw ValidationException::withMessages(['evidencia' => 'Esta incidencia ya tiene un respaldo.']);
                }
                $incidencia->update([
                    'archivo' => $path, 'archivo_nombre' => Str::limit($archivo->getClientOriginalName(), 240, ''),
                    'archivo_subido_por' => auth()->id(), 'archivo_subido_en' => now(),
                ]);
                app(Auditoria::class)->registrar('asignaciones', 'documentar_incidencia', $incidencia->id);
            }, 3);
        } catch (\Throwable $e) {
            DocumentoPrivado::descartar($path);
            throw $e;
        }

        $prefijo = $this->asignacionesRoutePrefix();
        $destino = app(Permisos::class)->ruta($request->user(), $prefijo.'.asignaciones.incidencia')
            ? route($prefijo.'.asignaciones.incidencia', $incidencia) : route('dashboard');

        return redirect()->to($destino)->with('success', 'Respaldo de incidencia guardado.');
    }

    public function archivoIncidencia(IncidenciaInventario $incidencia)
    {
        $this->autorizarAsignacionInventario($incidencia->asignacion, 'asignaciones.archivos');
        abort_if($incidencia->archivo === null, 404);

        return DocumentoPrivado::responder($incidencia->archivo, $incidencia->archivo_nombre);
    }

    private function datosArchivo(UploadedFile $archivo, string $ruta, int $asignacion, string $tipo): array
    {
        return [
            'asignacion_inventario_id' => $asignacion, 'tipo_documento' => $tipo, 'ruta' => $ruta,
            'nombre_original' => Str::limit($archivo->getClientOriginalName(), 240, ''),
            'mime' => $archivo->getMimeType(), 'tamano' => $archivo->getSize(), 'subido_por_user_id' => auth()->id(),
        ];
    }

    private function filasDelGrupo(string $grupo, string $permiso = 'asignaciones.consultar', bool $bloquear = false)
    {
        $filas = AsignacionInventario::where('grupo_asignacion', $grupo)->orderBy('id')->get();
        $alcance = $this->inventario->autorizarCustodias(auth()->user(), $permiso, $filas, $bloquear);
        if ($bloquear || request()->isMethod('GET')) {
            $this->inventario->registrar($alcance, $permiso, $grupo);
        }

        return $filas;
    }

    private function volverAEntrega(string $grupo, string $mensaje)
    {
        $prefijo = $this->asignacionesRoutePrefix();
        $destino = app(Permisos::class)->ruta(auth()->user(), $prefijo.'.asignaciones.entrega')
            ? route($prefijo.'.asignaciones.entrega', $grupo) : route('dashboard');

        return redirect()->to($destino)->with('success', $mensaje);
    }

    public function pdf(string $grupo)
    {
        return app(\App\Services\HojaAsignacion::class)->vista(auth()->user(), $grupo);
    }

    public function uploadPdfFirmado(Request $request, AsignacionInventario $asignacion)
    {
        $this->autorizarAsignacionInventario($asignacion, 'asignaciones.evidencias');

        if ($asignacion->entrega !== null) {
            return back()->with('error', 'Carga los respaldos desde el detalle de entrega.');
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

                $this->autorizarAsignacionInventario($asignacion, 'asignaciones.evidencias', bloquear: true);
                foreach ($evidencias as $evidencia) {
                    AsignacionInventarioArchivo::create($evidencia);
                    if ($evidencia['tipo_documento'] === 'asignacion_firmada') {
                        $asignacion->pdf_firmado = $evidencia['ruta'];
                    }
                }

                $tienePdf = $asignacion->pdfsAsignacionFirmados()->exists();
                $asignacion->estado_evidencia = $tienePdf ? 'completa' : 'pendiente';
                $asignacion->save();
            }, 3);
        } catch (\Throwable $e) {
            foreach ($evidencias as $evidencia) {
                DocumentoPrivado::descartar($evidencia['ruta']);
            }

            throw $e;
        }

        $prefijo = $this->asignacionesRoutePrefix();
        $destino = app(Permisos::class)->ruta($request->user(), $prefijo.'.asignaciones.index')
            ? route($prefijo.'.asignaciones.index') : route('dashboard');

        return redirect()->to($destino)->with('success', 'Respaldo histórico guardado.');
    }

    public function uploadPdfDevolucionFirmado(Request $request, string $grupo)
    {
        if (!Schema::hasTable('asignacion_inventario_archivos')) {
            return back()->with('error', 'La carga de hojas de devolución firmadas no está disponible. Comunícate con soporte.');
        }

        $movimientos = $this->movimientosDevolucionAutorizados($grupo, 'asignaciones.evidencias');

        if ($movimientos->isEmpty()) {
            abort(404, 'No se encontraron movimientos de devolución para este grupo.');
        }

        $data = $request->validate([
            'pdf_firmado' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $data['pdf_firmado'];
        $path = DocumentoPrivado::guardar($file, 'asignaciones/firmados/devoluciones/' . $grupo);

        try {
            DB::transaction(function () use ($movimientos, $grupo, $file, $path) {
                $alcance = $this->inventario->autorizarCustodias(auth()->user(), 'asignaciones.evidencias', $movimientos->pluck('asignacion'), bloquear: true);
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
                $this->inventario->registrar($alcance, 'asignaciones.evidencias', $grupo);
            }, 3);
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
            $movimientos = $this->movimientosDevolucionAutorizados((string) $archivo->grupo_devolucion, 'asignaciones.archivos');

            if ($movimientos->isEmpty()) {
                abort(403);
            }
        } elseif ($archivo->grupo_entrega !== null) {
            $this->filasDelGrupo($archivo->grupo_entrega, 'asignaciones.archivos');
        } else {
            $archivo->loadMissing('asignacion');

            if (!$archivo->asignacion) {
                abort(404);
            }

            $this->autorizarAsignacionInventario($archivo->asignacion, 'asignaciones.archivos');
        }

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    public function devolver(Request $request, AsignacionInventario $asignacion)
    {
        $data = $request->validate($this->reglasDevolucion() + [
            'cantidad_devuelta' => ['required', 'integer', 'min:1'],
        ]);

        return $this->procesarDevoluciones($request, $data, AsignacionInventario::whereKey($asignacion->id),
            fn () => (int) $data['cantidad_devuelta']);
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
            fn ($asignacion) => (int) ($data['devoluciones'][$asignacion->id] ?? 0));
    }

    public function devolverTodoColaborador(Request $request, string $colaboradorCodigo)
    {
        $data = $request->validate($this->reglasDevolucion());
        $query = AsignacionInventario::custodiaAbierta()->where('colaborador_codigo', $colaboradorCodigo);
        $this->inventario->custodiasParaResolverTodo($query, $request->user());

        return $this->procesarDevoluciones($request, $data, $query, fn ($asignacion) => $asignacion->cantidad_asignada);
    }

    private function reglasDevolucion(): array
    {
        return [
            'solicitud_id' => ['required', 'uuid'],
            'motivo_administrativo' => ['nullable', 'in:ordinaria,renuncia,despido'],
            'resoluciones' => ['nullable', 'array', 'max:100'],
            'resoluciones.*' => ['required', 'array'],
        ] + ResolverCustodia::reglas() + ResolverCustodia::reglas('resoluciones.*.');
    }

    private function procesarDevoluciones(Request $request, array $data, $query, callable $cantidadPedida)
    {
        $grupo = (string) Str::uuid();
        $usuario = $request->user();

        $motivo = $data['motivo_administrativo'] ?? 'ordinaria';
        [$ids, $alertas, $retorno] = DB::transaction(function () use ($data, $query, $cantidadPedida, $grupo, $usuario, $motivo) {
            SolicitudInventarioService::registrarDevolucion($data['solicitud_id']);
            // Se usa el mismo orden de bloqueo que en las salidas y reemplazos.
            $codigos = (clone $query)->distinct()->pluck('colaborador_codigo');
            Colaborador::whereIn('codigo', $codigos)->orderBy('codigo')->lockForUpdate()->get();
            $asignaciones = (clone $query)->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get();
            if ($asignaciones->isEmpty()) {
                throw ValidationException::withMessages(['cantidad_devuelta' => 'No hay custodias activas para resolver.']);
            }

            $alcance = $this->inventario->autorizarCustodias($usuario, 'asignaciones.devolver', $asignaciones, bloquear: true);
            $this->inventario->registrar($alcance, 'asignaciones.devolver', $grupo);
            $alertas = [];
            $tuvoRetorno = false;
            foreach ($asignaciones as $asignacion) {
                $condicion = $data['resoluciones'][$asignacion->id] ?? $data;
                $resultado = $this->resolverCustodia->aplicar($asignacion, $cantidadPedida($asignacion), $condicion, $usuario, $grupo, $motivo);
                $tuvoRetorno = $tuvoRetorno || $resultado['retorno'];
                if ($resultado['alerta'] !== null) {
                    $alertas[] = $resultado['alerta'];
                }
            }

            return [$asignaciones->modelKeys(), $alertas, $tuvoRetorno];
        }, 3);
        $this->notificarAlertas($alertas, $usuario);
        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                AsignacionInventario::whereKey($ids)->get(), $usuario, 'una resolución de custodia'
            )
        );

        $prefijo = $this->asignacionesRoutePrefix();
        $destino = app(Permisos::class)->ruta($usuario, $prefijo.'.asignaciones.index')
            ? route($prefijo.'.asignaciones.index') : route('dashboard');

        return redirect()->to($destino)->with('success', 'Custodia actualizada correctamente.')
            ->with('grupo_devolucion', $retorno && $motivo !== 'ordinaria' ? $grupo : null);
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

    private function autorizarAsignacionInventario(AsignacionInventario $asignacion, string $permiso = 'asignaciones.consultar', bool $bloquear = false): void
    {
        $alcance = $this->inventario->autorizarCustodias(auth()->user(), $permiso, collect([$asignacion]), $bloquear);
        if ($bloquear || request()->isMethod('GET')) {
            $this->inventario->registrar($alcance, $permiso, $asignacion->id);
        }
    }

    private function asignacionesRoutePrefix(): string
    {
        return AccesoInventarioOperativo::prefijo(auth()->user());
    }

    private function movimientosDevolucionAutorizados(string $grupo, string $permiso = 'asignaciones.exportar')
    {
        $query = AsignacionMovimiento::with([
            'asignacion',
            'asignacion.colaborador',
            'asignacion.producto',
            'asignacion.bodega',
            'user',
        ])
            ->whereIn('tipo', ['Devolucion', 'Reemplazo'])
            ->where('grupo_devolucion', $grupo)
            ->where(fn ($q) => $q->whereNull('motivo_administrativo')->orWhereIn('motivo_administrativo', ['renuncia', 'despido']))
            ->orderBy('created_at');

        $filas = $query->get();
        if ($filas->isNotEmpty()) {
            $alcance = $this->inventario->autorizarCustodias(auth()->user(), $permiso, $filas->pluck('asignacion'));
            if (request()->isMethod('GET')) {
                $this->inventario->registrar($alcance, $permiso, $grupo);
            }
        }

        return $filas;
    }
}
