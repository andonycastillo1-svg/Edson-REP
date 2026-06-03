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
use Carbon\Carbon;
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

        $vehiculos = Vehiculo::orderBy('marca')
            ->orderBy('placa')
            ->get();

        $asignaciones = VehiculoProductoAsignacion::with([
                'vehiculo',
                'producto',
                'bodega',
                'asignadoPor',
                'colaboradorResponsable',
                'asignacionVehiculo.colaborador',
                'cerradoPor',
            ])
            ->when($vehiculoVin, fn ($query) => $query->where('vehiculo_vin', $vehiculoVin))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $inventariosQuery = Inventario::with(['producto', 'bodega'])
            ->where('cantidad', '>', 0)
            ->whereHas('producto', function ($query) {
                $query->whereRaw('LOWER(TRIM(categoria)) = ?', ['refacciones']);
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
            return back()
                ->withInput()
                ->with('error', 'No tienes permiso para asignar productos de esa bodega.');
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

                if (strtolower(trim((string) optional($inventario->producto)->categoria)) !== 'refacciones') {
                    throw new \RuntimeException('Solo se pueden asignar productos con categoría Refacciones.');
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
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.vehiculos.productos.index', ['vehiculo_vin' => $data['vehiculo_vin']])
            ->with('success', 'Producto/refacción asignado al vehículo correctamente.');
    }

    public function pdfAsignacion(VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess)
    {
        $this->autorizarDocumento($asignacion, $bodegaAccess);

        $asignacion->load([
            'vehiculo',
            'producto',
            'bodega',
            'asignadoPor',
            'asignacionVehiculo.colaborador',
        ]);

        return view('admin.vehiculos.productos.pdf', [
            'asignacion' => $asignacion,
            'tipoDocumento' => 'asignacion',
        ]);
    }

    public function pdfDevolucion(VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess)
    {
        $this->autorizarDocumento($asignacion, $bodegaAccess);

        if ($asignacion->activa) {
            return back()->with('error', 'La hoja de devolución solo está disponible cuando el producto/refacción ya fue retirado o cerrado.');
        }

        $asignacion->load([
            'vehiculo',
            'producto',
            'bodega',
            'cerradoPor',
            'colaboradorResponsable',
            'asignacionVehiculo.colaborador',
        ]);

        return view('admin.vehiculos.productos.pdf', [
            'asignacion' => $asignacion,
            'tipoDocumento' => 'devolucion',
        ]);
    }

    private function autorizarDocumento(VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess): void
    {
        if (!$bodegaAccess->canView(auth()->user(), (int) $asignacion->bodega_id)) {
            abort(403);
        }
    }

    public function cerrar(Request $request, VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess)
    {
        $data = $request->validate([
            'cantidad_cierre' => ['required', 'integer', 'min:1'],
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

                if (!$asignacion->activa) {
                    throw new \RuntimeException('La asignación del producto ya está cerrada.');
                }

                $cantidadActual = (int) $asignacion->cantidad;
                $cantidadCerrar = (int) $data['cantidad_cierre'];

                if ($cantidadCerrar <= 0) {
                    throw new \RuntimeException('La cantidad a retirar debe ser mayor a 0.');
                }

                if ($cantidadCerrar > $cantidadActual) {
                    throw new \RuntimeException('No puedes retirar más cantidad de la que está activa en el vehículo.');
                }

                $accion = $data['accion_cierre'];
                $estado = $accion === 'regresar' ? 'regresado' : $accion;
                $malUso = $accion === 'danado' && (bool) ($data['mal_uso_colaborador'] ?? false);

                $asignacionCerrada = $this->obtenerRegistroCierre($asignacion, $cantidadCerrar, $estado, $accion, $malUso, $data['observaciones_cierre'] ?? null);

                if ($accion === 'regresar') {
                    $this->regresarCantidadAInventario($asignacion, $cantidadCerrar);
                }

                $responsable = null;
                $alertaGenerada = false;

                if ($malUso) {
                    $vehiculoAsignado = AsignacionVehiculo::where('vehiculo_vin', $asignacion->vehiculo_vin)
                        ->where('activa', true)
                        ->latest('fecha_inicio')
                        ->first();

                    $responsable = $vehiculoAsignado?->colaborador_codigo;

                    if ($responsable && Schema::hasTable('alertas_reemplazos_rrhh')) {
                        $alertaGenerada = $this->generarAlertaMalUsoVehiculo(
                            asignacion: $asignacionCerrada,
                            colaboradorCodigo: $responsable,
                            cantidadCerrar: $cantidadCerrar,
                            observaciones: $data['observaciones_cierre'] ?? null
                        );
                    }
                }

                $asignacionCerrada->update([
                    'colaborador_responsable_codigo' => $responsable,
                    'descuento_generado' => $alertaGenerada,
                ]);

                if ($cantidadCerrar === $cantidadActual) {
                    $asignacion->update([
                        'estado' => $asignacionCerrada->estado,
                        'activa' => false,
                        'cerrado_por_user_id' => auth()->id(),
                        'fecha_cierre' => now(),
                        'accion_cierre' => $accion,
                        'mal_uso_colaborador' => $malUso,
                        'colaborador_responsable_codigo' => $responsable,
                        'descuento_generado' => $alertaGenerada,
                        'observaciones' => $asignacionCerrada->observaciones,
                    ]);
                } else {
                    $asignacion->update([
                        'cantidad' => $cantidadActual - $cantidadCerrar,
                        'estado' => 'activo',
                        'activa' => true,
                    ]);
                }
            });
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Producto/refacción cerrado correctamente.');
    }

    private function obtenerRegistroCierre(
        VehiculoProductoAsignacion $asignacion,
        int $cantidadCerrar,
        string $estado,
        string $accion,
        bool $malUso,
        ?string $observacionesCierre
    ): VehiculoProductoAsignacion {
        $cantidadActual = (int) $asignacion->cantidad;

        $observaciones = trim((string) ($asignacion->observaciones ?? ''));
        $observacionesCierre = trim((string) ($observacionesCierre ?? ''));

        $textoCierre = 'Cierre: acción=' . $accion . ', cantidad=' . $cantidadCerrar;

        if ($observacionesCierre !== '') {
            $textoCierre .= ', observación=' . $observacionesCierre;
        }

        $observacionesFinal = trim($observaciones . ($observaciones ? ' | ' : '') . $textoCierre);

        if ($cantidadCerrar === $cantidadActual) {
            $asignacion->fill([
                'estado' => $estado,
                'activa' => false,
                'cerrado_por_user_id' => auth()->id(),
                'fecha_cierre' => now(),
                'accion_cierre' => $accion,
                'mal_uso_colaborador' => $malUso,
                'observaciones' => $observacionesFinal ?: $asignacion->observaciones,
            ]);

            return $asignacion;
        }

        $cerrada = $asignacion->replicate();

        $cerrada->cantidad = $cantidadCerrar;
        $cerrada->estado = $estado;
        $cerrada->activa = false;
        $cerrada->cerrado_por_user_id = auth()->id();
        $cerrada->fecha_cierre = now();
        $cerrada->accion_cierre = $accion;
        $cerrada->mal_uso_colaborador = $malUso;
        $cerrada->observaciones = $observacionesFinal ?: $asignacion->observaciones;

        $cerrada->created_at = now();
        $cerrada->updated_at = now();

        $cerrada->save();

        return $cerrada;
    }

    private function regresarCantidadAInventario(VehiculoProductoAsignacion $asignacion, int $cantidadCerrar): void
    {
        $inventario = Inventario::firstOrCreate(
            [
                'producto_codigo' => $asignacion->producto_codigo,
                'bodega_id' => $asignacion->bodega_id,
            ],
            ['cantidad' => 0]
        );

        $inventario->increment('cantidad', $cantidadCerrar);

        Movimiento::create([
            'producto_codigo' => $asignacion->producto_codigo,
            'bodega_origen_id' => null,
            'bodega_destino_id' => $asignacion->bodega_id,
            'tipo_movimiento' => 'Entrada',
            'cantidad' => $cantidadCerrar,
            'fecha' => now(),
            'user_id' => auth()->id(),
            'vehiculo_vin' => $asignacion->vehiculo_vin,
        ]);
    }

    private function generarAlertaMalUsoVehiculo(
        VehiculoProductoAsignacion $asignacion,
        string $colaboradorCodigo,
        int $cantidadCerrar,
        ?string $observaciones
    ): bool {
        $costoUnitario = $this->ultimoCostoUnitario($asignacion->producto_codigo);
        $vidaUtilMeses = (int) ($asignacion->producto->vida_util_meses ?? 0);

        $fechaAsignacion = $asignacion->fecha
            ? Carbon::parse($asignacion->fecha)->startOfDay()
            : now()->startOfDay();

        $fechaDano = now()->startOfDay();

        $mesesUsados = $fechaAsignacion->diffInMonths($fechaDano);
        $mesesRestantes = $vidaUtilMeses > 0
            ? max(0, $vidaUtilMeses - $mesesUsados)
            : 0;

        $costoTotal = $costoUnitario !== null
            ? round($costoUnitario * $cantidadCerrar, 2)
            : null;

        $montoCobro = ($costoTotal !== null && $vidaUtilMeses > 0)
            ? round($costoTotal * ($mesesRestantes / $vidaUtilMeses), 2)
            : 0;

        AlertaReemplazo::create([
            'colaborador_codigo' => $colaboradorCodigo,
            'producto_codigo' => $asignacion->producto_codigo,
            'vehiculo_vin' => $asignacion->vehiculo_vin,
            'vehiculo_producto_asignacion_id' => $asignacion->id,
            'cantidad' => $cantidadCerrar,
            'costo_estimado' => $costoTotal,
            'registrado_por_user_id' => auth()->id(),
            'fecha_asignacion_anterior' => $asignacion->fecha ?? now(),
            'fecha_dano_reemplazo' => now(),
            'vida_util_meses' => $vidaUtilMeses,
            'meses_restantes' => $mesesRestantes,
            'descuento_aplicable' => $montoCobro > 0,
            'estado' => 'pendiente',
            'detalle' => $this->detalleAlertaVehiculo(
                asignacion: $asignacion,
                observaciones: $observaciones,
                costoUnitario: $costoUnitario,
                cantidadCerrar: $cantidadCerrar,
                vidaUtilMeses: $vidaUtilMeses,
                mesesRestantes: $mesesRestantes,
                montoCobro: $montoCobro
            ),
        ]);

        return true;
    }

    private function ultimoCostoUnitario(string $productoCodigo): ?float
    {
        if (!Schema::hasTable('compra_detalles') || !Schema::hasColumn('compra_detalles', 'precio_unitario')) {
            return null;
        }

        $query = DB::table('compra_detalles as cd')
            ->where('cd.producto_codigo', $productoCodigo);

        if (Schema::hasTable('compras') && Schema::hasColumn('compra_detalles', 'compra_id')) {
            $query->leftJoin('compras as c', 'c.id', '=', 'cd.compra_id');

            if (Schema::hasColumn('compras', 'fecha_compra')) {
                $query->orderByDesc('c.fecha_compra');
            }
        }

        $precio = $query
            ->orderByDesc('cd.id')
            ->value('cd.precio_unitario');

        return $precio !== null ? (float) $precio : null;
    }

    private function detalleAlertaVehiculo(
        VehiculoProductoAsignacion $asignacion,
        ?string $observaciones,
        ?float $costoUnitario,
        int $cantidadCerrar,
        int $vidaUtilMeses,
        int $mesesRestantes,
        float $montoCobro
    ): string {
        $vehiculo = trim(
            ($asignacion->vehiculo->marca ?? 'Vehículo') .
            ' ' .
            ($asignacion->vehiculo->placa ?? '') .
            ' VIN ' .
            $asignacion->vehiculo_vin
        );

        $producto = $asignacion->producto->nombre ?? $asignacion->producto_codigo;

        $costo = $costoUnitario !== null
            ? ' Costo unitario según última compra: Q ' . number_format($costoUnitario, 2) . '.'
            : ' No se encontró precio/costo histórico suficiente para calcular costo automático.';

        return 'Daño por mal uso registrado desde Productos del vehículo.'
            . ' Vehículo: ' . $vehiculo . '.'
            . ' Producto/refacción: ' . $producto . '.'
            . ' Cantidad dañada: ' . $cantidadCerrar . '.'
            . ' Vida útil: ' . $vidaUtilMeses . ' meses.'
            . ' Meses restantes: ' . $mesesRestantes . '.'
            . ' Cobro calculado: Q ' . number_format($montoCobro, 2) . '.'
            . $costo
            . ($observaciones ? ' Observaciones: ' . $observaciones : '');
    }
}   