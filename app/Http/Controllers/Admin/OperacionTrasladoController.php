<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Operacion;
use App\Models\OperacionDetalle;
use App\Models\Producto;
use App\Services\BodegaAccessService;
use App\Services\InventarioStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperacionTrasladoController extends Controller
{
    public function __construct(
        private BodegaAccessService $bodegaAccess,
        private InventarioStockService $stockService
    ) {
    }

    public function index(Request $request)
    {
        $estado  = $request->get('estado');   // PENDIENTE/APROBADO/RECHAZADO/null
        $origen  = $request->get('origen');
        $destino = $request->get('destino');

        $user = auth()->user();

        $q = Operacion::with(['bodegaOrigen','bodegaDestino','creador'])
            ->where('tipo', Operacion::TIPO_TRASLADO);

        if ((int) $user->role_id !== 1) {
            $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
            if ($visibleBodegaIds === []) {
                $q->whereRaw('1 = 0');
            } elseif (is_array($visibleBodegaIds)) {
                $q->where(function ($query) use ($visibleBodegaIds, $user) {
                    $query->whereIn('bodega_origen_id', $visibleBodegaIds)
                        ->orWhereIn('bodega_destino_id', $visibleBodegaIds)
                        ->orWhere('creado_por', $user->id);
                });
            }
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
        $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
        $bodegas = Bodega::query()
            ->when(is_array($visibleBodegaIds), fn ($q) => $visibleBodegaIds === []
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('id', $visibleBodegaIds))
            ->orderBy('nombre')
            ->get();

        return view('admin.traslados.index', compact('operaciones','bodegas','estado','origen','destino','user'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $visibleBodegaIds = $this->bodegaAccess->visibleBodegaIds($user);
        $bodegas = Bodega::query()
            ->when(is_array($visibleBodegaIds), fn ($q) => $visibleBodegaIds === []
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('id', $visibleBodegaIds))
            ->orderBy('nombre')
            ->get();
        $productos = Producto::orderBy('nombre')->get();
        $origenId  = $request->get('origen');

        if ($origenId && !$this->bodegaAccess->canModifyStock($user, (int) $origenId)) {
            abort(403);
        }

        return view('admin.traslados.create', compact('bodegas','productos','origenId'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ((int) $user->role_id === 2) {
            $request->merge([
                'bodega_origen_id' => $user->bodega_id,
            ]);
        }

        $data = $request->validate([
            'bodega_origen_id'  => ['required', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id' => ['required', 'exists:bodegas,id'],
            'observacion' => ['nullable','string','max:2000'],
            'lineas'                   => ['required','array','min:1'],
            'lineas.*.producto_codigo' => ['required','exists:productos,codigo'],
            'lineas.*.cantidad'        => ['required','integer','min:1'],
        ], [
            'lineas.required' => 'Debes agregar al menos un producto.'
        ]);

        if (!$this->bodegaAccess->canModifyStock($user, (int) $data['bodega_origen_id'])
            || !$this->bodegaAccess->canReceiveTransfer($user, (int) $data['bodega_destino_id'])) {
            abort(403);
        }

        $agrupadas = [];
        foreach ($data['lineas'] as $l) {
            $cod  = $l['producto_codigo'];
            $cant = (int) $l['cantidad'];
            $agrupadas[$cod] = ($agrupadas[$cod] ?? 0) + $cant;
        }

        $origenId = (int) $data['bodega_origen_id'];
        foreach ($agrupadas as $codigo => $cantidad) {
            $inv = Inventario::where('producto_codigo', $codigo)
                ->where('bodega_id', $origenId)
                ->first();
            $disponible = (int) (optional($inv)->cantidad ?? 0);

            if ($disponible < $cantidad) {
                throw ValidationException::withMessages([
                    'lineas' => "Stock insuficiente para {$codigo}. Disponible: {$disponible}, solicitado: {$cantidad}."
                ]);
            }
        }

        $op = DB::transaction(function () use ($data, $agrupadas) {
            $op = new Operacion();
            $op->forceFill([
                'tipo'              => Operacion::TIPO_TRASLADO,
                'estado'            => Operacion::ESTADO_PENDIENTE,
                'bodega_origen_id'  => (int) $data['bodega_origen_id'],
                'bodega_destino_id' => (int) $data['bodega_destino_id'],
                'creado_por'        => (int) auth()->id(),
                'observacion'       => $data['observacion'] ?? null,
            ]);
            $op->save();

            foreach ($agrupadas as $codigo => $cantidad) {
                OperacionDetalle::create([
                    'operacion_id'    => $op->id,
                    'producto_codigo' => $codigo,
                    'cantidad'        => (int) $cantidad,
                ]);
            }

            return $op;
        });

        $routePrefix = $user->role_id == 2 ? 'operador' : 'admin';

        return redirect()->route($routePrefix . '.operaciones.traslados.show', $op)
            ->with('ok', 'Solicitud de traslado creada. Queda pendiente de aprobación.');
    }

    public function show(Operacion $operacion)
    {
        $operacion->load([
            'bodegaOrigen','bodegaDestino','creador','aprobador','rechazador',
            'detalles.producto'
        ]);

        $user = auth()->user();

        if (!$this->canAccessOperacion($user, $operacion)) {
            abort(403);
        }

        $puedeDecidir = $this->bodegaAccess->canModifyStock($user, (int) $operacion->bodega_destino_id)
            && $operacion->estado === Operacion::ESTADO_PENDIENTE;

        return view('admin.traslados.show', compact('operacion','user','puedeDecidir'));
    }

    public function aprobar(Operacion $operacion)
    {
        $user = auth()->user();

        if (!$this->bodegaAccess->canModifyStock($user, (int) $operacion->bodega_destino_id)) {
            abort(403);
        }

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        DB::transaction(function () use ($operacion, $user) {
            $operacion = Operacion::query()
                ->whereKey($operacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'error' => 'Esta solicitud ya fue procesada.',
                ]);
            }

            $operacion->load('detalles');

            $origenId  = (int)$operacion->bodega_origen_id;
            $destinoId = (int)$operacion->bodega_destino_id;

            // Validar stock real + mover inventario + crear movimientos
            foreach ($operacion->detalles as $d) {
                $codigo   = $d->producto_codigo;
                $cantidad = (int)$d->cantidad;

                $this->stockService->descontar($origenId, $codigo, $cantidad);
                $this->stockService->incrementar($destinoId, $codigo, $cantidad);

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

        $routePrefix = $user->role_id == 2 ? 'operador' : 'admin';

        return redirect()->route($routePrefix . '.operaciones.traslados.show', $operacion)
            ->with('ok', 'Solicitud aprobada. Inventario actualizado.');
    }

    public function rechazar(Request $request, Operacion $operacion)
    {
        $user = auth()->user();

        if (!$this->bodegaAccess->canModifyStock($user, (int) $operacion->bodega_destino_id)) {
            abort(403);
        }

        if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $data = $request->validate([
            'motivo_rechazo' => ['required','string','min:3','max:2000'],
        ]);

        DB::transaction(function () use ($operacion, $user, $data) {
            $operacion = Operacion::query()
                ->whereKey($operacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operacion->estado !== Operacion::ESTADO_PENDIENTE) {
                throw ValidationException::withMessages([
                    'error' => 'Esta solicitud ya fue procesada.',
                ]);
            }

            $operacion->estado = Operacion::ESTADO_RECHAZADO;
            $operacion->rechazado_por = $user->id;
            $operacion->rechazado_en = now();
            $operacion->motivo_rechazo = $data['motivo_rechazo'];
            $operacion->save();
        });

        $routePrefix = $user->role_id == 2 ? 'operador' : 'admin';

        return redirect()->route($routePrefix . '.operaciones.traslados.show', $operacion)
            ->with('ok', 'Solicitud rechazada.');
    }

    public function hoja(Operacion $operacion)
    {
        $operacion->load(['bodegaOrigen','bodegaDestino','creador','detalles.producto']);

        $user = auth()->user();

        if (!$this->canAccessOperacion($user, $operacion)) {
            abort(403);
        }

        return view('admin.traslados.hoja', compact('operacion'));
    }

    private function canAccessOperacion($user, Operacion $operacion): bool
    {
        if ((int) $user->role_id === 1) {
            return true;
        }

        return (int) $operacion->creado_por === (int) $user->id
            || $this->bodegaAccess->canView($user, (int) $operacion->bodega_origen_id)
            || $this->bodegaAccess->canView($user, (int) $operacion->bodega_destino_id);
    }
}
