<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Auditoria;
use App\Sistema\Presentacion;
use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Sistema\ConsultaCampos;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Operacion;
use App\Models\OperacionDetalle;
use App\Models\Producto;
use App\Services\NotificacionService;
use App\Services\AccesoInventarioOperativo;
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use App\Services\SolicitudInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperacionTrasladoController extends Controller
{
    public function __construct(private AccesoInventarioOperativo $acceso) {}

    public function index(Request $request)
    {
        $estado  = $request->get('estado');
        $origen  = $request->get('origen');
        $destino = $request->get('destino');

        $user = auth()->user();

        $q = Operacion::with(['bodegaOrigen', 'bodegaDestino', 'creador'])
            ->where('tipo', Operacion::TIPO_TRASLADO);

        $permiso = $request->boolean('xlsx') ? 'traslados.datos_exportar' : 'traslados.consultar';
        $alcances = $this->acceso->alcances($user, $permiso);
        abort_if($alcances->isEmpty(), 403);
        $this->acceso->limitarTraslados($q, $alcances);
        $visibles = clone $q;

        $q->when($estado, function ($qq) use ($estado) {
            $qq->where('estado', $estado);
        })
            ->when($origen, function ($qq) use ($origen) {
                $qq->where('bodega_origen_id', $origen);
            })
            ->when($destino, function ($qq) use ($destino) {
                $qq->where('bodega_destino_id', $destino);
            })
            ->orderByDesc('created_at');

        app(ConsultaCampos::class)->filtrar($q, $request, 'traslados');
        if ($request->boolean('xlsx')) {
            $exportar = app(ExportacionesOperativas::class);
            $alcances->each(fn ($alcance) => $this->acceso->registrar($alcance, $permiso, 'traslados'));

            return $exportar->traslados($q);
        }
        $operaciones = $q->paginate(20)->withQueryString();
        $tablaCampos = app(Presentacion::class)->operativa('traslados', $request->user(), $operaciones->pluck('id'));
        $bodegas = Bodega::whereIn('id', (clone $visibles)->select('bodega_origen_id'))
            ->orWhereIn('id', (clone $visibles)->select('bodega_destino_id'))->orderBy('nombre')->get();

        return view('admin.traslados.index', compact(
            'operaciones',
            'tablaCampos',
            'bodegas',
            'estado',
            'origen',
            'destino'
        ));
    }

    public function create(Request $request)
    {
        $usuario = $request->user();
        $alcances = $this->acceso->alcances($usuario, 'traslados.crear');
        $origenes = Bodega::whereKey($this->acceso->bodegasPara($usuario, 'traslados.crear'))->orderBy('nombre')->get();
        $bodegas = Bodega::query()->when(!$alcances->contains('historico', true), fn ($q) => $q->whereKey($origenes->modelKeys()))
            ->orderBy('nombre')->get();
        $productos = Producto::orderBy('nombre')->get();
        $origenId = $origenes->count() === 1 ? $origenes->sole()->id : $request->integer('origen');

        return view('admin.traslados.create', compact('bodegas', 'productos', 'origenId', 'origenes'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $alcances = $this->acceso->alcances($user, 'traslados.crear');
        if ($user->bodega_id && $alcances->count() === 1 && $alcances->sole()->historico && $alcances->sole()->bodegas !== null) {
            $request->merge(['bodega_origen_id' => $user->bodega_id]);
        }

        $data = $request->validate([
            'solicitud_id'           => ['nullable', 'uuid'],
            'bodega_origen_id'       => ['required', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id'      => ['required', 'exists:bodegas,id'],
            'observacion'            => ['nullable', 'string', 'max:2000'],
            'archivo_excel'          => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],

            'lineas'                 => ['required', 'array', 'min:1', 'max:100'],
            'lineas.*.producto_codigo' => ['required', 'exists:productos,codigo'],
            'lineas.*.cantidad'      => ['required', 'integer', 'min:1', 'max:10000'],
        ], [
            'lineas.required' => 'Debes agregar al menos un producto.',
        ]);

        $this->acceso->solicitudTraslado($user, (int) $data['bodega_origen_id'], (int) $data['bodega_destino_id']);

        $agrupadas = [];

        foreach ($data['lineas'] as $linea) {
            $codigo = $linea['producto_codigo'];
            $cantidad = (int) $linea['cantidad'];

            $agrupadas[$codigo] = ($agrupadas[$codigo] ?? 0) + $cantidad;
        }

        /*
        |--------------------------------------------------------------------------
        | Validar stock disponible en origen
        |--------------------------------------------------------------------------
        */

        $origenId = (int) $data['bodega_origen_id'];

        foreach ($agrupadas as $codigo => $cantidad) {
            $inventario = Inventario::where('producto_codigo', $codigo)
                ->where('bodega_id', $origenId)
                ->where('stock_tipo', 'nuevo')
                ->first();

            $disponible = (int) (optional($inventario)->cantidad ?? 0);

            if ($disponible < $cantidad) {
                throw ValidationException::withMessages([
                    'lineas' => "Stock insuficiente para {$codigo}. Disponible: {$disponible}, solicitado: {$cantidad}.",
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Crear solicitud y detalle
        |--------------------------------------------------------------------------
        */

        app(Presentacion::class)->validar($request, 'traslados', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'traslados', true);

        $archivoExcelPath = null;
        $archivoExcelNombre = null;

        if ($request->hasFile('archivo_excel')) {
            $archivo = $request->file('archivo_excel');
            $archivoExcelPath = DocumentoPrivado::guardar($archivo, 'traslados');
            $archivoExcelNombre = $archivo->getClientOriginalName();
        }

        try {
            $operacion = DB::transaction(function () use ($data, $agrupadas, $archivoExcelPath, $archivoExcelNombre, $campos, $valores, $user) {
                $alcance = $this->acceso->solicitudTraslado($user, (int) $data['bodega_origen_id'], (int) $data['bodega_destino_id'], bloquear: true);
                SolicitudInventarioService::registrar('traslado', $data['solicitud_id'] ?? null);
                $operacion = new Operacion([
                    'tipo'                 => Operacion::TIPO_TRASLADO,
                    'estado'               => Operacion::ESTADO_PENDIENTE,
                    'bodega_origen_id'     => (int) $data['bodega_origen_id'],
                    'bodega_destino_id'    => (int) $data['bodega_destino_id'],
                    'creado_por'           => (int) auth()->id(),
                    'observacion'          => $data['observacion'] ?? null,
                    'archivo_excel_path'   => $archivoExcelPath,
                    'archivo_excel_nombre' => $archivoExcelNombre,
                ]);

                $operacion->save();

                foreach ($agrupadas as $codigo => $cantidad) {
                    OperacionDetalle::create([
                        'operacion_id'    => $operacion->id,
                        'producto_codigo' => $codigo,
                        'cantidad'        => (int) $cantidad,
                    ]);
                }

                $this->acceso->registrar($alcance, 'traslados.crear', $operacion->id);
                $campos->guardar('traslados', $operacion->id, $valores);
                app(Auditoria::class)->registrar('traslados', 'solicitar', $operacion->id, null, [
                    'origen' => $operacion->bodega_origen_id, 'destino' => $operacion->bodega_destino_id,
                ]);
                return $operacion;
            }, 3);
        } catch (\Throwable $e) {
            if ($archivoExcelPath !== null) {
                DocumentoPrivado::descartar($archivoExcelPath);
            }

            throw $e;
        }

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarNuevoTraslado($operacion, $user)
        );

        $routePrefix = AccesoInventarioOperativo::prefijo($user);

        $ruta = $this->acceso->tiene($user, 'traslados.consultar') ? $routePrefix.'.operaciones.traslados.show' : $routePrefix.'.operaciones.traslados.create';

        return redirect()
            ->route($ruta, str_ends_with($ruta, '.show') ? [$operacion] : [])
            ->with('ok', 'Solicitud de traslado creada. Queda pendiente de aprobación.');
    }

    public function show(Operacion $operacion)
    {
        $this->autorizarVerOperacion($operacion);
        $operacion->load([
            'bodegaOrigen',
            'bodegaDestino',
            'creador',
            'aprobador',
            'rechazador',
            'detalles.producto',
        ]);

        $user = auth()->user();

        $pendiente = $operacion->estado === Operacion::ESTADO_PENDIENTE;
        $puedeAprobar = $pendiente && $this->acceso->decidirTraslado($user, $operacion, 'traslados.aprobar');
        $puedeRechazar = $pendiente && $this->acceso->decidirTraslado($user, $operacion, 'traslados.rechazar');
        $puedeDecidir = $puedeAprobar || $puedeRechazar;

        return view('admin.traslados.show', compact(
            'operacion',
            'puedeDecidir', 'puedeAprobar', 'puedeRechazar'
        ));
    }

    public function aprobar(Operacion $operacion, InventarioStockService $stockService)
    {
        $user = auth()->user();

        DB::transaction(function () use ($operacion, $user, $stockService) {
            $operacion = $this->bloquearPendiente($operacion, 'traslados.aprobar');
            $origenId = (int) $operacion->bodega_origen_id;
            $destinoId = (int) $operacion->bodega_destino_id;

            foreach ($operacion->detalles()->orderBy('producto_codigo')->get() as $detalle) {
                $codigo = $detalle->producto_codigo;
                $cantidad = (int) $detalle->cantidad;
                $stockService->trasladar($origenId, $destinoId, $codigo, $cantidad);

                Movimiento::create([
                    'producto_codigo'   => $codigo,
                    'bodega_origen_id'  => $origenId,
                    'bodega_destino_id' => $destinoId,
                    'tipo_movimiento'   => 'Traslado',
                    'cantidad'          => $cantidad,
                    'fecha'             => now(),
                    'user_id'           => $user->id,
                ]);
            }

            $operacion->estado = Operacion::ESTADO_APROBADO;
            $operacion->aprobado_por = $user->id;
            $operacion->aprobado_en = now();
            $operacion->save();
            app(Auditoria::class)->registrar('traslados', 'aprobar', $operacion->id, ['estado' => Operacion::ESTADO_PENDIENTE], ['estado' => $operacion->estado]);
        }, 3);

        return $this->respuestaDecision($operacion, 'Solicitud aprobada. Inventario actualizado.');
    }

    public function rechazar(Request $request, Operacion $operacion)
    {
        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $operacion) {
            $operacion = $this->bloquearPendiente($operacion, 'traslados.rechazar');
            $operacion->estado = Operacion::ESTADO_RECHAZADO;
            $operacion->rechazado_por = auth()->id();
            $operacion->rechazado_en = now();
            $operacion->motivo_rechazo = $data['motivo_rechazo'];
            $operacion->save();
            app(Auditoria::class)->registrar('traslados', 'rechazar', $operacion->id, ['estado' => Operacion::ESTADO_PENDIENTE], ['estado' => $operacion->estado]);
        }, 3);

        return $this->respuestaDecision($operacion, 'Solicitud rechazada.');
    }

    private function bloquearPendiente(Operacion $operacion, string $permiso): Operacion
    {
        $operacion = Operacion::whereKey($operacion->id)->lockForUpdate()->firstOrFail();
        abort_unless($operacion->tipo === Operacion::TIPO_TRASLADO, 404);
        $user = auth()->user();
        $alcance = $this->acceso->decidirTraslado($user, $operacion, $permiso, bloquear: true) ?? abort(403);

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages(['error' => 'Esta solicitud ya fue procesada.']);
        }

        $this->acceso->registrar($alcance, $permiso, $operacion->id);

        return $operacion;
    }

    private function respuestaDecision(Operacion $operacion, string $mensaje)
    {
        $user = auth()->user();
        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoTraslado($operacion->fresh(), $user)
        );

        $routePrefix = AccesoInventarioOperativo::prefijo($user);

        $ruta = $this->acceso->tiene($user, 'traslados.consultar') ? $routePrefix.'.operaciones.traslados.show' : 'dashboard';

        return redirect()->route($ruta, $ruta === 'dashboard' ? [] : [$operacion])->with('ok', $mensaje);
    }

    public function archivo(Operacion $operacion)
    {
        $this->autorizarVerOperacion($operacion, 'traslados.archivos');
        abort_unless($operacion->archivo_excel_path, 404, 'El archivo adjunto no existe.');

        return DocumentoPrivado::responder($operacion->archivo_excel_path, $operacion->archivo_excel_nombre, true);
    }

    private function autorizarVerOperacion(Operacion $operacion, string $permiso = 'traslados.consultar'): void
    {
        abort_unless($operacion->tipo === Operacion::TIPO_TRASLADO, 404);
        $alcance = $this->acceso->trasladoVisible(auth()->user(), $operacion, $permiso) ?? abort(403);
        $this->acceso->registrar($alcance, $permiso, $operacion->id);
    }

    public function hoja(Operacion $operacion)
    {
        $operacion->load([
            'bodegaOrigen',
            'bodegaDestino',
            'creador', 'aprobador',
            'detalles.producto',
        ]);

        $this->autorizarVerOperacion($operacion, 'traslados.exportar');

        $formato = app(\App\Services\FormatosDocumento::class)->emitir('traslado', (string) $operacion->id);

        return view('admin.traslados.hoja', compact('operacion', 'formato'));
    }
}