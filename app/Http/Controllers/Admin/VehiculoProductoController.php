<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Presentacion;
use App\Sistema\Auditoria;
use App\Http\Controllers\Controller;
use App\Services\NotificacionService;
use App\Models\AlertaReemplazo;
use App\Models\AsignacionVehiculo;
use App\Models\Bodega;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use App\Models\VehiculoProductoArchivo;
use App\Services\BodegaAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Producto;
use App\Services\DocumentoPrivado;
use App\Services\InventarioStockService;
use App\Services\SolicitudInventarioService;
use Illuminate\Validation\ValidationException;
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
                'pdfAsignacionFirmado',
                'pdfDevolucionFirmado',
            ])
            ->when($vehiculoVin, fn ($query) => $query->where('vehiculo_vin', $vehiculoVin))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $inventariosQuery = Inventario::with(['producto', 'bodega'])
            ->disponiblesAgrupados()
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

    public function store(Request $request, BodegaAccessService $bodegaAccess, InventarioStockService $stock)
    {
        $data = $request->validate([
            'vehiculo_vin' => ['required', 'exists:vehiculos,vin'],
            'producto_codigo' => ['required', 'exists:productos,codigo'],
            'bodega_id' => ['required', 'exists:bodegas,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
            'fecha' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string'],
        ]);

        if (!$bodegaAccess->canModifyStock(auth()->user(), (int) $data['bodega_id'])) {
            return back()
                ->withInput()
                ->with('error', 'No tienes permiso para asignar productos de esa bodega.');
        }

        app(Presentacion::class)->validar($request, 'refacciones', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'refacciones', true);

        try {
            $asignacion = DB::transaction(function () use ($data, $stock, $campos, $valores) {
                Vehiculo::whereKey($data['vehiculo_vin'])->lockForUpdate()->firstOrFail();
                $producto = Producto::findOrFail($data['producto_codigo']);
                if (strtolower(trim((string) $producto->categoria)) !== 'refacciones') {
                    throw ValidationException::withMessages(['producto_codigo' => 'Solo se pueden asignar productos con categoría Refacciones.']);
                }

                $asignacionActiva = AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])
                    ->where('activa', true)
                    ->latest('fecha_inicio')
                    ->first();

                $asignacion = VehiculoProductoAsignacion::create([
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

                $stock->asignarAVehiculo($asignacion);

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

                $campos->guardar('refacciones', $asignacion->id, $valores);
                app(Auditoria::class)->registrar('refacciones', 'asignar', $asignacion->id, null, ['cantidad' => $asignacion->cantidad]);
                return $asignacion;
            }, 3);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo asignar la refacción.');
        }

        try {
            app(NotificacionService::class)->notificarNuevaAsignacion(
                $asignacion,
                $request->user(),
                route('admin.vehiculos.productos.index', ['vehiculo_vin' => $data['vehiculo_vin']])
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('admin.vehiculos.productos.index', ['vehiculo_vin' => $data['vehiculo_vin']])
            ->with('success', 'Producto/refacción asignado al vehículo correctamente.')
            ->with('pdf_url', route('admin.vehiculos.productos.pdf_asignacion', $asignacion));
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

    public function cerrar(Request $request, VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess, InventarioStockService $stock)
    {
        $data = $request->validate([
            'solicitud_id' => ['nullable', 'uuid'],
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
            $alertaRrhh = DB::transaction(function () use ($data, $asignacion, $stock) {
                SolicitudInventarioService::registrar('cierre_refaccion', $data['solicitud_id'] ?? null);
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

                $stock->cerrarDeVehiculo($asignacion, $cantidadCerrar, $accion);
                $asignacionCerrada = $this->obtenerRegistroCierre($asignacion, $cantidadCerrar, $estado, $accion, $malUso, $data['observaciones_cierre'] ?? null);

                if ($accion === 'regresar') {
                    Movimiento::create([
                        'producto_codigo' => $asignacion->producto_codigo,
                        'bodega_destino_id' => $asignacion->bodega_id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => $cantidadCerrar,
                        'fecha' => now(),
                        'user_id' => auth()->id(),
                        'vehiculo_vin' => $asignacion->vehiculo_vin,
                    ]);
                }

                $responsable = null;
                $alertaGenerada = null;

                if ($malUso) {
                    $responsable = $asignacion->asignacionVehiculo?->colaborador_codigo;

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
                    'descuento_generado' => (bool) $alertaGenerada,
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
                        'descuento_generado' => (bool) $alertaGenerada,
                        'observaciones' => $asignacionCerrada->observaciones,
                    ]);
                } else {
                    $asignacion->update([
                        'cantidad' => $cantidadActual - $cantidadCerrar,
                        'estado' => 'activo',
                        'activa' => true,
                    ]);
                }

                app(Auditoria::class)->registrar('refacciones', 'cerrar', $asignacion->id, ['cantidad_activa' => $cantidadActual], [
                    'cantidad_activa' => $cantidadActual - $cantidadCerrar, 'accion' => $accion,
                ]);
                return $alertaGenerada;
            }, 3);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo cerrar la refacción.');
        }

        if ($alertaRrhh instanceof AlertaReemplazo) {
            try {
                app(NotificacionService::class)->notificarAlertaRrhh($alertaRrhh, $request->user());
            } catch (\Throwable $exception) {
                report($exception);
            }
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

    public function subirPdfFirmado(Request $request, VehiculoProductoAsignacion $asignacion, BodegaAccessService $bodegaAccess)
    {
        $this->autorizarDocumento($asignacion, $bodegaAccess);
        $data = $request->validate([
            'tipo_documento' => ['required', 'in:asignacion_firmada,devolucion_firmada'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($data['tipo_documento'] === 'devolucion_firmada' && $asignacion->activa) {
            throw ValidationException::withMessages(['tipo_documento' => 'La refacción aún está activa.']);
        }

        $file = $data['archivo'];
        $ruta = DocumentoPrivado::guardar($file, 'vehiculos/productos/' . $asignacion->id);
        try {
            $asignacion->archivosFirmados()->create([
                'tipo_documento' => $data['tipo_documento'],
                'ruta' => $ruta,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'tamano' => $file->getSize(),
                'subido_por_user_id' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            DocumentoPrivado::descartar($ruta);
            throw $e;
        }

        return back()->with('success', 'PDF firmado subido correctamente.');
    }

    public function verPdfFirmado(VehiculoProductoArchivo $archivo, BodegaAccessService $bodegaAccess)
    {
        $this->autorizarDocumento($archivo->asignacion()->firstOrFail(), $bodegaAccess);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    private function generarAlertaMalUsoVehiculo(
        VehiculoProductoAsignacion $asignacion,
        string $colaboradorCodigo,
        int $cantidadCerrar,
        ?string $observaciones
    ): AlertaReemplazo {
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

        return AlertaReemplazo::create([
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