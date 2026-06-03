<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Operacion;
use App\Models\OperacionDetalle;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        if ((int) $user->role_id === 1) {
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

        $operaciones = $q->paginate(20)->withQueryString();
        $bodegas = Bodega::orderBy('nombre')->get();

        return view('admin.traslados.index', compact(
            'operaciones',
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

        if ((int) $user->role_id === 2) {
            $request->merge([
                'bodega_origen_id' => $user->bodega_id,
            ]);
        }

        $data = $request->validate([
            'bodega_origen_id'  => ['required', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id' => ['required', 'exists:bodegas,id'],
            'observacion'       => ['nullable', 'string', 'max:2000'],
            'archivo_excel'    => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],

            'lineas'                   => ['required', 'array', 'min:1'],
            'lineas.*.producto_codigo' => ['required', 'exists:productos,codigo'],
            'lineas.*.cantidad'        => ['required', 'integer', 'min:1'],
        ], [
            'lineas.required' => 'Debes agregar al menos un producto.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validación extra para evitar manipulación
        |--------------------------------------------------------------------------
        */

        if ((int) $user->role_id === 2 && (int) $data['bodega_origen_id'] !== (int) $user->bodega_id) {
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

        $archivoExcelPath = null;
        $archivoExcelNombre = null;

        if ($request->hasFile('archivo_excel')) {
            $archivo = $request->file('archivo_excel');
            $archivoExcelPath = $archivo->store('traslados', 'public');
            $archivoExcelNombre = $archivo->getClientOriginalName();
        }

        $operacion = DB::transaction(function () use ($data, $agrupadas, $archivoExcelPath, $archivoExcelNombre) {
            $operacion = new Operacion();

            $operacion->forceFill([
                'tipo'              => Operacion::TIPO_TRASLADO,
                'estado'            => Operacion::ESTADO_PENDIENTE,
                'bodega_origen_id'  => (int) $data['bodega_origen_id'],
                'bodega_destino_id' => (int) $data['bodega_destino_id'],
                'creado_por'        => (int) auth()->id(),
                'observacion'       => $data['observacion'] ?? null,
                'archivo_excel_path' => $archivoExcelPath,
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

            return $operacion;
        });

        $routePrefix = (int) $user->role_id === 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.operaciones.traslados.show', $operacion)
            ->with('ok', 'Solicitud de traslado creada. Queda pendiente de aprobación.');
    }

    public function show(Operacion $operacion)
    {
        $operacion->load([
            'bodegaOrigen',
            'bodegaDestino',
            'creador',
            'aprobador',
            'rechazador',
            'detalles.producto',
        ]);

        $user = auth()->user();

        $esAdmin = (int) $user->role_id === 1;

        $esCreador = (int) $user->id === (int) $operacion->creado_por;

        $esDestinoEncargado = $user->isEncargado()
            && (int) $user->bodega_id === (int) $operacion->bodega_destino_id;

        /*
        |--------------------------------------------------------------------------
        | Puede ver:
        | - Admin
        | - Usuario que creó la solicitud
        | - Encargado de la bodega destino
        |--------------------------------------------------------------------------
        */

        if (!$esAdmin && !$esCreador && !$esDestinoEncargado) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | Puede decidir:
        | - Encargado de la bodega destino
        | - Solo si está pendiente
        |--------------------------------------------------------------------------
        */

        $puedeDecidir = $esDestinoEncargado
            && $operacion->estado === Operacion::ESTADO_PENDIENTE;

        return view('admin.traslados.show', compact(
            'operacion',
            'user',
            'puedeDecidir'
        ));
    }

    public function aprobar(Operacion $operacion)
    {
        $user = auth()->user();

        if (!$user->isEncargado() || (int) $user->bodega_id !== (int) $operacion->bodega_destino_id) {
            abort(403);
        }

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        DB::transaction(function () use ($operacion, $user) {
            $operacion->load('detalles');

            $origenId = (int) $operacion->bodega_origen_id;
            $destinoId = (int) $operacion->bodega_destino_id;

            foreach ($operacion->detalles as $detalle) {
                $codigo = $detalle->producto_codigo;
                $cantidad = (int) $detalle->cantidad;

                $inventarioOrigen = Inventario::where('producto_codigo', $codigo)
                    ->where('bodega_id', $origenId)
                    ->lockForUpdate()
                    ->first();

                $stock = (int) (optional($inventarioOrigen)->cantidad ?? 0);

                if ($stock < $cantidad) {
                    throw ValidationException::withMessages([
                        'error' => "Stock insuficiente en origen para {$codigo}. Disponible: {$stock}, solicitado: {$cantidad}.",
                    ]);
                }

                $inventarioOrigen->cantidad = $stock - $cantidad;
                $inventarioOrigen->save();

                $inventarioDestino = Inventario::where('producto_codigo', $codigo)
                    ->where('bodega_id', $destinoId)
                    ->lockForUpdate()
                    ->first();

                if (!$inventarioDestino) {
                    $inventarioDestino = Inventario::create([
                        'producto_codigo' => $codigo,
                        'bodega_id'       => $destinoId,
                        'cantidad'        => 0,
                    ]);
                }

                $inventarioDestino->cantidad = (int) $inventarioDestino->cantidad + $cantidad;
                $inventarioDestino->save();

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
        });

        $routePrefix = (int) $user->role_id === 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.operaciones.traslados.show', $operacion)
            ->with('ok', 'Solicitud aprobada. Inventario actualizado.');
    }

    public function rechazar(Request $request, Operacion $operacion)
    {
        $user = auth()->user();

        if (!$user->isEncargado() || (int) $user->bodega_id !== (int) $operacion->bodega_destino_id) {
            abort(403);
        }

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $operacion->estado = Operacion::ESTADO_RECHAZADO;
        $operacion->rechazado_por = $user->id;
        $operacion->rechazado_en = now();
        $operacion->motivo_rechazo = $data['motivo_rechazo'];
        $operacion->save();

        $routePrefix = (int) $user->role_id === 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.operaciones.traslados.show', $operacion)
            ->with('ok', 'Solicitud rechazada.');
    }

    public function archivo(Operacion $operacion)
    {
        $this->autorizarVerOperacion($operacion);

        if (!$operacion->archivo_excel_path || !Storage::disk('public')->exists($operacion->archivo_excel_path)) {
            abort(404, 'El archivo adjunto no existe.');
        }

        return response()->file(
            Storage::disk('public')->path($operacion->archivo_excel_path),
            [
                'Content-Disposition' => 'inline; filename="' . addslashes($operacion->archivo_excel_nombre ?? basename($operacion->archivo_excel_path)) . '"',
            ]
        );
    }

    private function autorizarVerOperacion(Operacion $operacion): void
    {
        $user = auth()->user();

        $esAdmin = (int) $user->role_id === 1;
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

        $user = auth()->user();

        $esAdmin = (int) $user->role_id === 1;

        $esCreador = (int) $user->id === (int) $operacion->creado_por;

        $esDestinoEncargado = $user->isEncargado()
            && (int) $user->bodega_id === (int) $operacion->bodega_destino_id;

        if (!$esAdmin && !$esCreador && !$esDestinoEncargado) {
            abort(403);
        }

        return view('admin.traslados.hoja', compact('operacion'));
    }
}