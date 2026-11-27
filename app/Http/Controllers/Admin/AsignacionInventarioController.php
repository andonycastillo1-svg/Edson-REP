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
use App\Services\DevolverCustodiaCompleta;
use App\Services\HojaDevolucion;
use App\Services\AccesoInventarioOperativo;
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignacionInventarioController extends Controller
{
    public function __construct(
        private AccesoInventarioOperativo $inventario,
        private InventarioStockService $stock,
        private AccesoCustodia $acceso,
    ) {}

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:190'],
            'por_pagina' => ['nullable', Rule::in([10, 25, 50])],
            'seguimiento' => ['nullable', Rule::in(['documentacion'])],
            'region_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
            'estado_documental' => ['nullable', Rule::in(['pendiente', 'completa', 'todos'])],
        ]);
        $usuario = $request->user();
        $routePrefix = $this->asignacionesRoutePrefix();
        $permiso = $request->boolean('xlsx') ? 'asignaciones.datos_exportar' : 'asignaciones.consultar';
        $consulta = $this->asignacionesVisibles($usuario, $permiso);
        $campos = app(ConsultaCampos::class);
        $consulta->whereHas('colaborador', fn ($q) => $campos->buscar($q, $filtros['q'] ?? null, ['nombre', 'codigo']));
        $campos->filtrar($consulta, $request, 'asignaciones');

        $hojaFirmada = fn ($q) => $q->where('tipo_documento', 'asignacion_firmada');
        $seguimiento = ($filtros['seguimiento'] ?? null) === 'documentacion';
        $regionesDocumentales = collect();
        $documentacion = EntregaInventario::query()
            ->whereHas('asignaciones', fn ($q) => $q->whereIn('asignaciones_inventarios.id', (clone $consulta)->select('asignaciones_inventarios.id')));
        $faltantes = (clone $documentacion)->whereDoesntHave('documentos', $hojaFirmada)->count();

        if ($seguimiento) {
            $regionesDocumentales = \App\Models\Region::whereIn('id', Colaborador::whereIn('codigo',
                (clone $consulta)->select('asignaciones_inventarios.colaborador_codigo'))->select('region_id'))
                ->orderBy('nombre')->get(['id', 'nombre']);
            $documentacion
                ->when($filtros['region_id'] ?? null, fn ($q, $id) => $q->whereHas('asignaciones.colaborador', fn ($p) => $p->where('region_id', $id)))
                ->when($filtros['desde'] ?? null, fn ($q, $fecha) => $q->where('salida_en', '>=', Carbon::parse($fecha)->startOfDay()))
                ->when($filtros['hasta'] ?? null, fn ($q, $fecha) => $q->where('salida_en', '<', Carbon::parse($fecha)->addDay()->startOfDay()));
            $estado = $filtros['estado_documental'] ?? 'pendiente';
            if ($estado === 'pendiente') {
                $documentacion->whereDoesntHave('documentos', $hojaFirmada);
            } elseif ($estado === 'completa') {
                $documentacion->whereHas('documentos', $hojaFirmada);
            }
        }

        if ($request->boolean('xlsx')) {
            $this->inventario->alcances($usuario, $permiso)->each(fn ($alcance) => $this->inventario->registrar($alcance, $permiso, 'asignaciones'));
            if ($seguimiento) {
                $consulta->whereIn('grupo_asignacion', $documentacion->select('entregas_inventario.id'));
            }

            $exportar = app(ExportacionesOperativas::class);

            return $request->boolean('devoluciones') ? $exportar->devoluciones($consulta) : $exportar->asignaciones($consulta);
        }

        $porPagina = (int) ($filtros['por_pagina'] ?? 10);
        $tablaCampos = app(Presentacion::class)->operativa('asignaciones', $usuario, collect());
        $permisos = app(Permisos::class);
        $puedeCrearAsignaciones = $permisos->ruta($usuario, $routePrefix.'.asignaciones.create');
        $puedeVerMovimientos = $permisos->ruta($usuario, $routePrefix.'.asignaciones.historial');
        $entregas = null;
        $entregasConsultables = collect();
        $colaboradores = null;

        if ($seguimiento) {
            $entregas = $documentacion->withExists(['documentos as hoja_firmada' => $hojaFirmada])->with(['asignaciones' => fn ($q) => $q
                ->whereIn('asignaciones_inventarios.id', (clone $consulta)->select('asignaciones_inventarios.id'))
                ->with(['colaborador.region', 'user'])->orderBy('id')])
                ->latest('salida_en')->orderBy('id')->paginate($porPagina)->withQueryString();
            $entregasConsultables = AsignacionInventario::whereIn('grupo_asignacion', $entregas->getCollection()->modelKeys())
                ->get()->groupBy('grupo_asignacion')->filter(fn ($filas) =>
                    $this->inventario->buscarCustodias($usuario, 'asignaciones.consultar', $filas) !== null)->keys();
        } else {
            $resumen = (clone $consulta)->reorder()->groupBy('asignaciones_inventarios.colaborador_codigo')
                ->select('asignaciones_inventarios.colaborador_codigo')
                ->selectRaw('COUNT(*) AS asignaciones_count')
                ->selectRaw("SUM(CASE WHEN estado = 'Activa' AND sustituida_por_id IS NULL THEN cantidad_asignada ELSE 0 END) AS cantidad_activa");
            $colaboradores = Colaborador::query()->joinSub($resumen, 'resumen', 'resumen.colaborador_codigo', '=', 'colaboradores.codigo')
                ->select('colaboradores.*', 'resumen.asignaciones_count', 'resumen.cantidad_activa')
                ->orderBy('nombre')->orderBy('codigo')->paginate($porPagina)->withQueryString();
        }

        return view('admin.asignaciones.index', compact('colaboradores', 'entregas', 'routePrefix', 'faltantes',
            'puedeCrearAsignaciones', 'puedeVerMovimientos', 'tablaCampos', 'seguimiento', 'porPagina', 'entregasConsultables', 'regionesDocumentales'));
    }

    public function colaborador(Request $request, string $codigo)
    {
        $datos = $request->validate(['por_pagina' => ['nullable', Rule::in([10, 25, 50])]]);
        $consulta = $this->asignacionesVisibles($request->user(), 'asignaciones.consultar')->where('colaborador_codigo', $codigo);
        abort_unless((clone $consulta)->exists(), 404);
        $colaborador = Colaborador::findOrFail($codigo);
        $routePrefix = $this->asignacionesRoutePrefix();
        $asignaciones = $consulta->with('producto', 'entrega')
            ->when(config('inventario.seguimiento_unidades'), fn ($q) => $q->with('periodosActivos'))
            ->latest('fecha')->orderByDesc('id')->paginate((int) ($datos['por_pagina'] ?? 10))->withQueryString();
        $vida = app(AsignacionVidaUtilService::class);
        $vigencias = $asignaciones->getCollection()->mapWithKeys(fn ($fila) => [$fila->id => $vida->restante($fila, now())]);
        $puedeDevolver = app(DevolverCustodiaCompleta::class)->puedeRegistrar($request->user(), $colaborador);

        return view('admin.asignaciones.colaborador', compact('colaborador', 'asignaciones', 'vigencias', 'routePrefix', 'puedeDevolver'));
    }

    public function articulo(Request $request, AsignacionInventario $asignacion)
    {
        $this->autorizarAsignacionInventario($asignacion);
        $asignacion->load('producto', 'colaborador', 'bodega', 'entrega.documentos', 'evidencias', 'incidencias.registrador');
        $routePrefix = $this->asignacionesRoutePrefix();
        $vida = app(AsignacionVidaUtilService::class)->restante($asignacion, now());
        $movimientos = $asignacion->movimientos()->with('user')->orderByRaw('COALESCE(ocurrio_en, created_at) DESC')
            ->orderByDesc('id')->paginate(10);
        $tablaCampos = app(Presentacion::class)->operativa('asignaciones', $request->user(), collect([$asignacion->id]));
        $grupo = $asignacion->grupo_asignacion;
        $filas = $grupo ? AsignacionInventario::where('grupo_asignacion', $grupo)->get() : collect();
        $puedeEntrega = $grupo && $this->inventario->buscarCustodias($request->user(), 'asignaciones.consultar', $filas) !== null;
        $puedePdf = $grupo && $this->inventario->buscarCustodias($request->user(), 'asignaciones.exportar', $filas) !== null;
        $puedeVerArchivos = $this->acceso->puedeVer($request->user(), $asignacion, 'asignaciones.archivos');
        $puedeAdjuntar = $this->acceso->puedeVer($request->user(), $asignacion, 'asignaciones.evidencias');

        return view('admin.asignaciones.articulo', compact('asignacion', 'routePrefix', 'vida', 'movimientos', 'tablaCampos',
            'puedeEntrega', 'puedePdf', 'puedeVerArchivos', 'puedeAdjuntar'));
    }

    public function historial(Request $request)
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')],
            'colaborador' => ['nullable', 'string', 'max:190'],
            'producto' => ['nullable', 'string', 'max:190'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'bodega' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in([10, 25, 50])],
        ]);
        $usuario = $request->user();
        $routePrefix = $this->asignacionesRoutePrefix();
        $permiso = $request->boolean('xlsx') ? 'asignaciones.datos_exportar' : 'asignaciones.consultar';
        $visibles = $this->asignacionesVisibles($usuario, $permiso);
        $campos = app(ConsultaCampos::class);
        $base = AsignacionMovimiento::whereIn('asignacion_inventario_id', (clone $visibles)->select('id'));
        $fecha = DB::raw('COALESCE(ocurrio_en, created_at)');
        $filtrados = (clone $base)
            ->when($datos['desde'] ?? null, fn ($q, $valor) => $q->whereDate($fecha, '>=', $valor))
            ->when($datos['hasta'] ?? null, fn ($q, $valor) => $q->whereDate($fecha, '<=', $valor))
            ->when($datos['tipo'] ?? null, fn ($q, $valor) => $q->where('tipo', $valor))
            ->when($datos['colaborador'] ?? null, fn ($q, $texto) => $q->whereHas('asignacion.colaborador',
                fn ($persona) => $campos->buscar($persona, $texto, ['codigo', 'nombre'])))
            ->when($datos['producto'] ?? null, fn ($q, $texto) => $q->whereHas('asignacion.producto',
                fn ($producto) => $campos->buscar($producto, $texto, ['codigo', 'nombre', 'descripcion'])))
            ->when($datos['bodega'] ?? null, fn ($q, $bodega) => $q->where(fn ($grupo) => $grupo
                ->where('bodega_retorno_id', $bodega)->orWhere(fn ($sinRetorno) => $sinRetorno->whereNull('bodega_retorno_id')
                    ->whereHas('asignacion', fn ($a) => $a->where('bodega_id', $bodega)))));
        if ($request->boolean('xlsx')) {
            $this->inventario->alcances($usuario, $permiso)->each(fn ($alcance) => $this->inventario->registrar($alcance, $permiso, 'asignaciones'));

            return app(ExportacionesOperativas::class)->devolucionesPorMovimiento($filtrados);
        }

        $tipos = (clone $base)->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo');
        $bodegas = Bodega::whereIn('id', (clone $visibles)->select('bodega_id'))
            ->orWhereIn('id', (clone $base)->whereNotNull('bodega_retorno_id')->select('bodega_retorno_id'))->orderBy('nombre')->get();
        $movimientos = $filtrados->with('asignacion.colaborador', 'asignacion.producto', 'asignacion.bodega', 'user', 'bodegaRetorno')
            ->orderByDesc($fecha)->orderByDesc('id')
            ->paginate((int) ($datos['por_pagina'] ?? 10))->withQueryString();

        $grupos = $movimientos->getCollection()->pluck('grupo_devolucion')->filter()->unique();
        $pdfsDevolucionFirmados = AsignacionInventarioArchivo::whereIn('grupo_devolucion', $grupos)
            ->where('tipo_documento', 'devolucion_firmada')->latest('id')->get()->unique('grupo_devolucion')->keyBy('grupo_devolucion');

        $gruposDevolucion = AsignacionMovimiento::with('asignacion')->whereIn('grupo_devolucion', $grupos)
            ->conHojaDevolucion()
            ->get()->groupBy('grupo_devolucion')->map(function ($filas) use ($usuario, $routePrefix) {
                $asignaciones = $filas->pluck('asignacion');
                $permisos = app(Permisos::class);
                $acciones = ['exportar' => 'hoja_devolucion', 'archivos' => 'ver_pdf_firmado', 'evidencias' => 'upload_pdf_devolucion_firmado'];

                return collect($acciones)->map(fn ($accion, $permiso) => $permisos->ruta($usuario, $routePrefix.'.asignaciones.'.$accion)
                    && $this->inventario->buscarCustodias($usuario, 'asignaciones.'.$permiso, $asignaciones) !== null)->all();
            });

        return view('admin.asignaciones.historial', compact('routePrefix', 'movimientos', 'tipos', 'bodegas',
            'pdfsDevolucionFirmados', 'gruposDevolucion'));
    }

    private function asignacionesVisibles(User $usuario, string $permiso)
    {
        abort_unless($this->inventario->tiene($usuario, $permiso), 403);
        $consulta = AsignacionInventario::query();
        $this->inventario->limitarAsignaciones($consulta, $usuario, $permiso);

        return $consulta;
    }

    public function create(Request $request, RequerimientosAsignacion $requerimientos)
    {
        if ($request->query('modo') === 'elegir') {
            return view('admin.asignaciones.modo', [
                'prefijo' => $this->asignacionesRoutePrefix(),
                'puedeImportar' => $this->inventario->tiene($request->user(), 'flujo.importar'),
            ]);
        }
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
        $consolidadosOrigen = $requerimientos->conPendientes($usuario)->with('corte:id,nombre', 'coordinador:id,name')->latest('id')->get(['id', 'nombre', 'corte_id', 'coordinador_id']);

        return view('admin.asignaciones.create', compact(
            'inventarios',
            'colaboradores',
            'bodegas', 'motivos', 'consolidadosOrigen'
        ));
    }

    public function participantes(Request $request, RequerimientosAsignacion $requerimientos)
    {
        $datos = $request->validate(['consolidado_id' => ['required', 'integer']]);
        $consolidado = $requerimientos->verificar($request->user(), $datos['consolidado_id']);
        $personas = $requerimientos->pendientes($consolidado, usuario: $request->user())
            ->map(fn ($necesidad) => $necesidad->participante->colaborador)->filter()
            ->unique('codigo')->sortBy('nombre')->values()->map->only(['codigo', 'nombre']);

        return response()->json($personas)->header('Cache-Control', 'private, no-store');
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
        $vida = app(AsignacionVidaUtilService::class);
        $custodias = $consulta->with('entrega', 'colaborador')
            ->when(config('inventario.seguimiento_unidades'), fn ($q) => $q->with('periodosActivos'))
            ->orderByDesc('id')->get()->map(fn ($fila) => [
                'id' => $fila->id, 'producto_codigo' => $fila->producto_codigo, 'fecha' => $fila->fecha,
                'cantidad_asignada' => $fila->cantidad_asignada, 'vida_restante' => $vida->restante($fila, now(), 1),
                'puede_resolver' => $this->acceso->puedeResolver($usuario, $fila),
            ]);
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
            'items.*.condicion_anterior' => ['nullable', 'array:estado_devolucion,resultado_otra,detalle_devolucion,fecha_incidente'],
        ] + ResolverCustodia::reglas('items.*.condicion_anterior.'), [
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
                $primeraHoja = collect($archivos)->contains('tipo_documento', 'asignacion_firmada') && $bloqueada->faltaHojaFirmada();
                $bloqueada->documentos()->createMany($archivos);
                if ($primeraHoja) {
                    $actor = auth()->user();
                    DB::afterCommit(fn () => app(NotificacionService::class)->safeAction(
                        fn ($servicio) => $servicio->notificarHojaFirmada($bloqueada, $actor)
                    ));
                }
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
        return redirect()->route($prefijo.'.asignaciones.articulo', $asignacion)
            ->with('success', 'Respaldo histórico guardado.');
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
            ->route($routePrefix . '.asignaciones.historial')
            ->with('success', 'PDF firmado de devolución cargado correctamente.');
    }

    public function verPdfFirmado(AsignacionInventarioArchivo $archivo)
    {
        if ($archivo->tipo_documento === AsignacionInventarioArchivo::DEVOLUCION_GENERADA) {
            return $this->descargarDevolucion(request(), $archivo->grupo_devolucion, app(DevolverCustodiaCompleta::class));
        }
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

    public function formularioDevolucion(Request $request, string $codigo, DevolverCustodiaCompleta $devolucion)
    {
        $usuario = $request->user();
        abort_unless($this->inventario->tiene($usuario, 'asignaciones.devolver'), 403);
        $colaborador = Colaborador::with('region')->findOrFail($codigo);
        $articulos = $devolucion->cargar($usuario, $colaborador);
        $rutaExpediente = \App\Sistema\CatalogoPermisos::prefijo($usuario->rolOperativo()).'.colaboradores.expediente';
        $volver = app(Permisos::class)->ruta($usuario, $rutaExpediente) ? route($rutaExpediente, $colaborador) : route('dashboard');

        return view('admin.asignaciones.devolucion', compact('colaborador', 'articulos', 'volver'));
    }

    public function rechazarDevolucionParcial(): never
    {
        throw ValidationException::withMessages(['articulos' => 'Registra la devolución completa de la custodia del colaborador.']);
    }

    public function registrarDevolucionCompleta(Request $request, string $codigo, DevolverCustodiaCompleta $devolucion)
    {
        abort_unless($this->inventario->tiene($request->user(), 'asignaciones.devolver'), 403);
        if ($request->filled('articulos_json')) {
            $json = $request->validate(['articulos_json' => ['required', 'json']])['articulos_json'];
            $request->merge(['articulos' => json_decode($json, true, flags: JSON_THROW_ON_ERROR)]);
        }
        $datos = $request->validate([
            'solicitud_id' => ['required', 'uuid'],
            'articulos' => ['required', 'array', 'min:1'],
            'articulos.*' => ['required', 'array:buen_estado,danado,sin_retorno', 'required_array_keys:buen_estado,danado,sin_retorno'],
            'articulos.*.*' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ], [
            'articulos.*.*.required' => 'Indica una cantidad para cada condición. Usa cero si no aplica.',
        ]);
        $grupo = $devolucion->registrar($request->user(), $codigo, $datos['solicitud_id'], $datos['articulos']);

        return redirect()->route('devoluciones.pdf', $grupo);
    }

    public function descargarDevolucion(Request $request, string $grupo, DevolverCustodiaCompleta $devolucion)
    {
        $movimientos = HojaDevolucion::movimientos($grupo);
        abort_if($movimientos->isEmpty(), 404);
        $alcance = $devolucion->alcanceHoja($request->user(), $movimientos);
        abort_if($alcance === null, 403);
        $this->inventario->registrar($alcance, 'asignaciones.consultar_hoja_devolucion', $grupo);
        $guardado = AsignacionInventarioArchivo::where('grupo_devolucion', $grupo)
            ->where('tipo_documento', AsignacionInventarioArchivo::DEVOLUCION_GENERADA)->first();
        $descargar = $request->boolean('descargar');
        if ($guardado) {
            return DocumentoPrivado::responder($guardado->ruta, $guardado->nombre_original, $descargar);
        }

        return response(HojaDevolucion::pdf($movimientos, $grupo), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($descargar ? 'attachment' : 'inline').'; filename="devolucion-'.$grupo.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function hojaDevolucion(Request $request, string $grupo, DevolverCustodiaCompleta $devolucion)
    {
        return $this->descargarDevolucion($request, $grupo, $devolucion);
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
        return request()->routeIs('supervisor.asignaciones.*') ? 'supervisor' : AccesoInventarioOperativo::prefijo(auth()->user());
    }

    private function movimientosDevolucionAutorizados(string $grupo, string $permiso = 'asignaciones.exportar')
    {
        $filas = HojaDevolucion::movimientos($grupo);
        if ($filas->isNotEmpty()) {
            $alcance = $this->inventario->autorizarCustodias(auth()->user(), $permiso, $filas->pluck('asignacion'));
            if (request()->isMethod('GET')) {
                $this->inventario->registrar($alcance, $permiso, $grupo);
            }
        }

        return $filas;
    }
}
