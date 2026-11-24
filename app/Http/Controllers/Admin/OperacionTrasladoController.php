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
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use App\Services\SolicitudInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperacionTrasladoController extends Controller
{
    public function index(Request $request)
    {
        $estado  = $request->get('estado');
        $origen  = $request->get('origen');
        $destino = $request->get('destino');

        $user = auth()->user();

        $q = Operacion::with(['bodegaOrigen', 'bodegaDestino', 'creador'])
            ->where('tipo', Operacion::TIPO_TRASLADO);

        /*
        |--------------------------------------------------------------------------
        | Permisos de visualización
        |--------------------------------------------------------------------------
        | Admin ve todo.
        | Operador/encargado ve:
        | - solicitudes donde su bodega es destino
        | - solicitudes que él creó
        */

        if ($user->rolOperativo() === 1) {
            // Admin ve todas.
        } elseif ($user->isEncargado()) {
            $q->where(function ($query) use ($user) {
                $query->where('bodega_destino_id', $user->bodega_id)
                    ->orWhere('creado_por', $user->id);
            });
        } else {
            $q->where('creado_por', $user->id);
        }

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
            $exportar->autorizar($request, 'traslados');

            return $exportar->traslados($q);
        }
        $operaciones = $q->paginate(20)->withQueryString();
        $tablaCampos = app(Presentacion::class)->operativa('traslados', $request->user(), $operaciones->pluck('id'));
        $bodegas = Bodega::orderBy('nombre')->get();

        return view('admin.traslados.index', compact(
            'operaciones',
            'tablaCampos',
            'bodegas',
            'estado',
            'origen',
            'destino',
            'user'
        ));
    }

    public function create(Request $request)
    {
        $bodegas   = Bodega::orderBy('nombre')->get();
        $productos = Producto::orderBy('nombre')->get();
        $origenId  = $request->get('origen');

        return view('admin.traslados.create', compact(
            'bodegas',
            'productos',
            'origenId'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Si es operador, forzar origen a su bodega asignada
        |--------------------------------------------------------------------------
        */

        if ($user->rolOperativo() === 2) {
            $request->merge([
                'bodega_origen_id' => $user->bodega_id,
            ]);
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

        /*
        |--------------------------------------------------------------------------
        | Validación extra para evitar manipulación
        |--------------------------------------------------------------------------
        */

        if ($user->rolOperativo() === 2 && (int) $data['bodega_origen_id'] !== (int) $user->bodega_id) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | Unificar productos repetidos
        |--------------------------------------------------------------------------
        */

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
            $operacion = DB::transaction(function () use ($data, $agrupadas, $archivoExcelPath, $archivoExcelNombre, $campos, $valores) {
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

        $routePrefix = $user->rolOperativo() === 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.operaciones.traslados.show', $operacion)
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

        $esDestinoEncargado = $user->isEncargado()
            && (int) $user->bodega_id === (int) $operacion->bodega_destino_id;

        $puedeDecidir = $esDestinoEncargado
            && $operacion->estado === Operacion::ESTADO_PENDIENTE;

        return view('admin.traslados.show', compact(
            'operacion',
            'user',
            'puedeDecidir'
        ));
    }

    public function aprobar(Operacion $operacion, InventarioStockService $stockService)
    {
        $user = auth()->user();

        DB::transaction(function () use ($operacion, $user, $stockService) {
            $operacion = $this->bloquearPendiente($operacion);
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
            $operacion = $this->bloquearPendiente($operacion);
            $operacion->estado = Operacion::ESTADO_RECHAZADO;
            $operacion->rechazado_por = auth()->id();
            $operacion->rechazado_en = now();
            $operacion->motivo_rechazo = $data['motivo_rechazo'];
            $operacion->save();
            app(Auditoria::class)->registrar('traslados', 'rechazar', $operacion->id, ['estado' => Operacion::ESTADO_PENDIENTE], ['estado' => $operacion->estado]);
        }, 3);

        return $this->respuestaDecision($operacion, 'Solicitud rechazada.');
    }

    private function bloquearPendiente(Operacion $operacion): Operacion
    {
        $operacion = Operacion::whereKey($operacion->id)->lockForUpdate()->firstOrFail();
        abort_unless($operacion->tipo === Operacion::TIPO_TRASLADO, 404);
        $user = auth()->user();
        abort_unless($user->isEncargado() && (int) $user->bodega_id === (int) $operacion->bodega_destino_id, 403);

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            throw ValidationException::withMessages(['error' => 'Esta solicitud ya fue procesada.']);
        }

        return $operacion;
    }

    private function respuestaDecision(Operacion $operacion, string $mensaje)
    {
        $user = auth()->user();
        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoTraslado($operacion->fresh(), $user)
        );

        $routePrefix = $user->rolOperativo() === 2 ? 'operador' : 'admin';

        return redirect()->route($routePrefix . '.operaciones.traslados.show', $operacion)->with('ok', $mensaje);
    }

    public function archivo(Operacion $operacion)
    {
        $this->autorizarVerOperacion($operacion);
        abort_unless($operacion->archivo_excel_path, 404, 'El archivo adjunto no existe.');

        return DocumentoPrivado::responder($operacion->archivo_excel_path, $operacion->archivo_excel_nombre, true);
    }

    private function autorizarVerOperacion(Operacion $operacion): void
    {
        abort_unless($operacion->tipo === Operacion::TIPO_TRASLADO, 404);
        $user = auth()->user();

        $esAdmin = $user->rolOperativo() === 1;
        $esCreador = (int) $user->id === (int) $operacion->creado_por;
        $esDestinoEncargado = $user->isEncargado()
            && (int) $user->bodega_id === (int) $operacion->bodega_destino_id;

        if (!$esAdmin && !$esCreador && !$esDestinoEncargado) {
            abort(403);
        }
    }

    public function hoja(Operacion $operacion)
    {
        $operacion->load([
            'bodegaOrigen',
            'bodegaDestino',
            'creador',
            'detalles.producto',
        ]);

        $this->autorizarVerOperacion($operacion);

        return view('admin.traslados.hoja', compact('operacion'));
    }
}