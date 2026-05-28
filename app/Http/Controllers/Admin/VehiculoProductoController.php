<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertaReemplazo;
use App\Models\AsignacionVehiculo;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use App\Services\BodegaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VehiculoProductoController extends Controller
{
    public function index(Request $request, BodegaAccessService $bodegaAccess)
    {
        $vehiculoVin = $request->query('vehiculo_vin');
        $vehiculo = $vehiculoVin ? Vehiculo::where('vin', $vehiculoVin)->first() : null;
        $usuario = auth()->user();
        $visibleBodegas = $bodegaAccess->visibleBodegaIds($usuario);

        $vehiculos = Vehiculo::orderBy('marca')->orderBy('placa')->get();

        $asignaciones = VehiculoProductoAsignacion::with(['vehiculo', 'producto', 'bodega', 'asignadoPor', 'colaboradorResponsable'])
            ->when($vehiculoVin, fn ($query) => $query->where('vehiculo_vin', $vehiculoVin))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $inventariosQuery = Inventario::with(['producto', 'bodega'])
            ->where('cantidad', '>', 0)
            ->whereHas('producto', function ($query) {
                $query->whereRaw('LOWER(TRIM(tipo)) = ?', ['refacciones']);
            })
            ->orderBy('producto_codigo');

        $bodegasQuery = Bodega::orderBy('nombre');

        if (is_array($visibleBodegas)) {
            $inventariosQuery->whereIn('bodega_id', $visibleBodegas);
            $bodegasQuery->whereIn('id', $visibleBodegas);
        }

        $inventarios = $inventariosQuery->get();
        $bodegas = $bodegasQuery->get();

        return view('admin.vehiculos.productos.index', compact(
            'asignaciones',
            'vehiculos',
            'vehiculo',
            'vehiculoVin',
            'inventarios',
            'bodegas'
        ));
    }

    public function store(Request $request, BodegaAccessService $bodegaAccess)
    {
        $data = $request->validate([
            'vehiculo_vin' => ['required', 'exists:vehiculos,vin'],
            'producto_codigo' => ['required', 'exists:productos,codigo'],
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (!$bodegaAccess->canModifyStock(auth()->user(), (int) $data['bodega_id'])) {
            return back()->withInput()->with('error', 'No tienes permiso para asignar productos de esa bodega.');
        }

        try {
            DB::transaction(function () use ($data) {
                $inventario = Inventario::with('producto')
                    ->where('producto_codigo', $data['producto_codigo'])
                    ->where('bodega_id', $data['bodega_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventario) {
                    throw new \RuntimeException('No existe inventario para el producto y bodega seleccionados.');
                }

                if (strtolower(trim((string) optional($inventario->producto)->tipo)) !== 'refacciones') {
                    throw new \RuntimeException('Solo se pueden asignar productos con tipo Refacciones.');
                }

                if ((int) $inventario->cantidad < (int) $data['cantidad']) {
                    throw new \RuntimeException('Stock insuficiente para asignar la cantidad solicitada.');
                }

                $asignacionActiva = AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])
                    ->where('activa', true)
                    ->latest('fecha_inicio')
                    ->first();

                $inventario->decrement('cantidad', (int) $data['cantidad']);

                VehiculoProductoAsignacion::create([
                    'asignacion_vehiculo_id' => $asignacionActiva?->id,
                    'vehiculo_vin' => $data['vehiculo_vin'],
                    'producto_codigo' => $data['producto_codigo'],
                    'bodega_id' => (int) $data['bodega_id'],
                    'cantidad' => (int) $data['cantidad'],
                    'tipo_control' => 'cantidad',
                    'serial' => null,
                    'fecha' => $data['fecha'],
                    'motivo' => $data['motivo'],
                    'observaciones' => $data['observaciones'] ?? null,
                    'estado' => 'activo',
                    'activa' => true,
                    'asignado_por_user_id' => auth()->id(),
                ]);

                Movimiento::create([
                    'producto_codigo' => $data['producto_codigo'],
                    'bodega_origen_id' => (int) $data['bodega_id'],
                    'bodega_destino_id' => null,
                    'tipo_movimiento' => 'Salida',
                    'cantidad' => (int) $data['cantidad'],
                    'fecha' => now(),
                    'user_id' => auth()->id(),
                    'vehiculo_vin' => $data['vehiculo_vin'],
                ]);
            });
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.vehiculos.productos.index', ['vehiculo_vin' => $data['vehiculo_vin']])
            ->with('success', 'Producto/refacción asignado al vehículo correctamente.');
    }

    public function cerrar(Request $request, VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess)
    {
        $data = $request->validate([
            'accion_cierre' => ['required', 'in:regresar,consumido,danado,baja'],
            'observaciones_cierre' => ['nullable', 'string'],
            'mal_uso_colaborador' => ['nullable', 'boolean'],
        ]);

        if (!$asignacion->activa) {
            return back()->with('error', 'La asignación del producto ya está cerrada.');
        }

        if ($data['accion_cierre'] === 'regresar' && !$bodegaAccess->canReceiveStock(auth()->user(), (int) $asignacion->bodega_id)) {
            return back()->with('error', 'No tienes permiso para regresar stock a esa bodega.');
        }

        try {
            DB::transaction(function () use ($data, $asignacion) {
                $asignacion = VehiculoProductoAsignacion::with(['producto', 'vehiculo'])
                    ->lockForUpdate()
                    ->findOrFail($asignacion->id);

                $accion = $data['accion_cierre'];

                if ($accion === 'regresar') {
                    $inventario = Inventario::firstOrCreate(
                        [
                            'producto_codigo' => $asignacion->producto_codigo,
                            'bodega_id' => $asignacion->bodega_id,
                        ],
                        ['cantidad' => 0]
                    );

                    $inventario->increment('cantidad', (int) $asignacion->cantidad);

                    Movimiento::create([
                        'producto_codigo' => $asignacion->producto_codigo,
                        'bodega_origen_id' => null,
                        'bodega_destino_id' => $asignacion->bodega_id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => (int) $asignacion->cantidad,
                        'fecha' => now(),
                        'user_id' => auth()->id(),
                        'vehiculo_vin' => $asignacion->vehiculo_vin,
                    ]);
                }

                $estado = $accion === 'regresar' ? 'regresado' : $accion;
                $malUso = $accion === 'danado' && (bool) ($data['mal_uso_colaborador'] ?? false);
                $responsable = null;
                $alertaGenerada = false;

                if ($malUso) {
                    $vehiculoAsignado = AsignacionVehiculo::where('vehiculo_vin', $asignacion->vehiculo_vin)
                        ->where('activa', true)
                        ->latest('fecha_inicio')
                        ->first();

                    $responsable = $vehiculoAsignado?->colaborador_codigo;

                    if ($responsable && Schema::hasTable('alertas_reemplazos_rrhh')) {
                        $costoUnitario = $this->ultimoCostoUnitario($asignacion->producto_codigo);

                        AlertaReemplazo::create([
                            'colaborador_codigo' => $responsable,
                            'producto_codigo' => $asignacion->producto_codigo,
                            'vehiculo_vin' => $asignacion->vehiculo_vin,
                            'vehiculo_producto_asignacion_id' => $asignacion->id,
                            'cantidad' => (int) $asignacion->cantidad,
                            'costo_estimado' => $costoUnitario ? $costoUnitario * (int) $asignacion->cantidad : null,
                            'registrado_por_user_id' => auth()->id(),
                            'fecha_asignacion_anterior' => $asignacion->fecha ?? now(),
                            'fecha_dano_reemplazo' => now(),
                            'vida_util_meses' => 0,
                            'meses_restantes' => 0,
                            'descuento_aplicable' => true,
                            'estado' => 'pendiente',
                            'detalle' => $this->detalleAlertaVehiculo($asignacion, $data['observaciones_cierre'] ?? null, $costoUnitario),
                        ]);

                        $alertaGenerada = true;
                    }
                }

                $observaciones = trim((string) ($asignacion->observaciones ?? ''));
                $observacionesCierre = trim((string) ($data['observaciones_cierre'] ?? ''));

                if ($observacionesCierre !== '') {
                    $observaciones = trim($observaciones . ($observaciones ? ' | ' : '') . 'Cierre: ' . $observacionesCierre);
                }

                $asignacion->update([
                    'estado' => $estado,
                    'activa' => false,
                    'cerrado_por_user_id' => auth()->id(),
                    'fecha_cierre' => now(),
                    'accion_cierre' => $accion,
                    'mal_uso_colaborador' => $malUso,
                    'colaborador_responsable_codigo' => $responsable,
                    'descuento_generado' => $alertaGenerada,
                    'observaciones' => $observaciones ?: $asignacion->observaciones,
                ]);
            });
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Producto/refacción cerrado correctamente.');
    }

    private function ultimoCostoUnitario(string $productoCodigo): ?float
    {
        if (!Schema::hasTable('compra_detalles') || !Schema::hasColumn('compra_detalles', 'precio_unitario')) {
            return null;
        }

        $precio = DB::table('compra_detalles as cd')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where('cd.producto_codigo', $productoCodigo)
            ->orderByDesc('c.fecha_compra')
            ->orderByDesc('cd.id')
            ->value('cd.precio_unitario');

        return $precio !== null ? (float) $precio : null;
    }

    private function detalleAlertaVehiculo(VehiculoProductoAsignacion $asignacion, ?string $observaciones, ?float $costoUnitario): string
    {
        $vehiculo = trim(($asignacion->vehiculo->marca ?? 'Vehículo') . ' ' . ($asignacion->vehiculo->placa ?? '') . ' VIN ' . $asignacion->vehiculo_vin);
        $producto = $asignacion->producto->nombre ?? $asignacion->producto_codigo;
        $costo = $costoUnitario !== null
            ? ' Costo estimado unitario según última compra: $' . number_format($costoUnitario, 2) . '.'
            : ' No se encontró precio/costo histórico suficiente para calcular descuento automático.';

        return 'Daño por mal uso registrado desde Productos del vehículo. Vehículo: ' . $vehiculo
            . '. Producto/refacción: ' . $producto
            . '. Cantidad: ' . $asignacion->cantidad . '.'
            . $costo
            . ($observaciones ? ' Observaciones: ' . $observaciones : '');
    }
}
