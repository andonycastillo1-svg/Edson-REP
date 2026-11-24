<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificacionService;
use App\Models\AsignacionVehiculo;
use App\Models\AsignacionVehiculoArchivo;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentoPrivado;
use App\Services\ExportarExcel;
use App\Services\ExportacionesOperativas;
use App\Services\InventarioStockService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AsignacionVehiculoController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('xlsx')) {
            app(ExportacionesOperativas::class)->autorizar($request, 'vehiculos');
            $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:190'], 'estado' => ['nullable', 'in:todos,activa,cerrada']]);
            $estado = $datos['estado'] ?? 'todos';
            $buscar = strtolower(trim($datos['buscar'] ?? ''));
            $filas = AsignacionVehiculo::with('vehiculo', 'colaborador')
                ->when($estado !== 'todos', fn ($q) => $q->where('activa', $estado === 'activa'))
                ->latest('fecha_inicio')->latest('id')->lazy(250)->filter(fn ($fila) => str_contains(strtolower(implode(' ', [
                    $fila->vehiculo?->marca, $fila->vehiculo?->modelo, $fila->vehiculo?->placa, $fila->vehiculo_vin,
                    $fila->colaborador?->nombre, $fila->colaborador_codigo,
                ])), $buscar))->map(fn ($fila) => [
                    $fila->vehiculo_vin, $fila->vehiculo?->placa, $fila->colaborador_codigo, $fila->colaborador?->nombre,
                    $fila->fecha_inicio, $fila->fecha_fin, $fila->activa ? 'Activa' : 'Cerrada',
                    $fila->estado_inicial_vehiculo, $fila->estado_final_vehiculo,
                ]);

            return app(ExportarExcel::class)->descargar('asignaciones-de-vehiculos', [
                'VIN', 'Placa', 'Código de colaborador', 'Colaborador', 'Desde', 'Hasta', 'Estado', 'Condición de salida', 'Condición de devolución',
            ], $filas);
        }
        $asignaciones = AsignacionVehiculo::with([
                'vehiculo',
                'colaborador',
                'productos.producto',
                'pdfAsignacionFirmado',
                'pdfDevolucionFirmado',
            ])
            ->latest('fecha_inicio')
            ->paginate(20);

        return view('admin.vehiculos.asignaciones.index', compact('asignaciones'));
    }

    public function create()
    {
        $vehiculos = Vehiculo::orderBy('marca')
            ->orderBy('placa')
            ->get();

        $colaboradores = Colaborador::where('estado', 'Activo')
            ->orderBy('nombre')
            ->get();

        $bodegas = Bodega::orderBy('nombre')
            ->get();

        $inventarios = Inventario::with(['producto', 'bodega'])
            ->disponiblesAgrupados()
            ->whereHas('producto', function ($query) {
                $query->whereRaw('LOWER(TRIM(categoria)) = ?', ['refacciones']);
            })
            ->orderBy('producto_codigo')
            ->get();

        return view('admin.vehiculos.asignaciones.create', compact(
            'vehiculos',
            'colaboradores',
            'inventarios',
            'bodegas'
        ));
    }

    public function store(Request $request, InventarioStockService $stock)
    {
        $data = $request->validate([
            'vehiculo_vin' => ['required', 'exists:vehiculos,vin'],
            'colaborador_codigo' => ['required', Rule::exists('colaboradores', 'codigo')->where('estado', 'Activo')],
            'fecha_inicio' => ['required', 'date'],
            'estado_inicial_vehiculo' => ['required', 'string', 'max:60'],
            'observaciones_asignacion' => ['nullable', 'string'],

            'productos' => ['nullable', 'array'],
            'productos.*.producto_codigo' => ['required_with:productos', 'exists:productos,codigo'],
            'productos.*.bodega_id' => ['required_with:productos', 'exists:bodegas,id'],
            'productos.*.cantidad' => ['required_with:productos', 'integer', 'min:1'],
            'productos.*.tipo_control' => ['nullable', 'in:cantidad'],
            'productos.*.observaciones' => ['nullable', 'string'],
        ]);

        $campos = app(\App\Sistema\ValoresCampos::class);
        $productos = $data['productos'] ?? [];
        foreach ($productos as $i => &$producto) {
            $entrada = $request->all('campos_refaccion')['campos_refaccion'][$i] ?? [];
            $producto['campos'] = $campos->preparar($request, 'refacciones', true, entrada: $entrada);
        }
        unset($producto);

        try {
            $asignacion = DB::transaction(function () use ($data, $stock, $productos, $campos) {
                Vehiculo::whereKey($data['vehiculo_vin'])->lockForUpdate()->firstOrFail();
                $colaborador = Colaborador::whereKey($data['colaborador_codigo'])->lockForUpdate()->firstOrFail();
                if ($colaborador->estado !== 'Activo'
                    || AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])->where('activa', true)->exists()
                    || AsignacionVehiculo::where('colaborador_codigo', $colaborador->codigo)->where('activa', true)->exists()) {
                    throw ValidationException::withMessages(['vehiculo_vin' => 'El vehículo o el colaborador ya tienen una asignación activa, o el colaborador está inactivo.']);
                }

                $asignacion = AsignacionVehiculo::create([
                    'vehiculo_vin' => $data['vehiculo_vin'],
                    'colaborador_codigo' => $data['colaborador_codigo'],
                    'asignado_por_user_id' => auth()->id(),
                    'fecha_inicio' => $data['fecha_inicio'],
                    'estado_inicial_vehiculo' => $data['estado_inicial_vehiculo'],
                    'observaciones_asignacion' => $data['observaciones_asignacion'] ?? null,
                    'activa' => true,
                ]);

                Vehiculo::where('vin', $data['vehiculo_vin'])
                    ->update(['estado' => 'En uso']);

                foreach (collect($productos)->sortBy('producto_codigo') as $prod) {
                    $productoCodigo = $prod['producto_codigo'];
                    $bodegaId = $prod['bodega_id'];
                    $cantidad = (int) $prod['cantidad'];

                    $producto = \App\Models\Producto::findOrFail($productoCodigo);
                    if (strtolower(trim((string) $producto->categoria)) !== 'refacciones') {
                        throw ValidationException::withMessages(['productos' => 'Solo se pueden asignar productos con categoría refacciones.']);
                    }

                    $refaccion = VehiculoProductoAsignacion::create([
                        'asignacion_vehiculo_id' => $asignacion->id,
                        'vehiculo_vin' => $data['vehiculo_vin'],
                        'producto_codigo' => $productoCodigo,
                        'bodega_id' => $bodegaId,
                        'cantidad' => $cantidad,
                        'tipo_control' => 'cantidad',
                        'serial' => null,
                        'fecha' => $data['fecha_inicio'],
                        'asignado_por_user_id' => auth()->id(),
                        'observaciones' => $prod['observaciones'] ?? null,
                        'activa' => true,
                    ]);

                    $stock->asignarAVehiculo($refaccion);
                    $campos->guardar('refacciones', $refaccion->id, $prod['campos']);

                    Movimiento::create([
                        'producto_codigo' => $productoCodigo,
                        'bodega_origen_id' => $bodegaId,
                        'bodega_destino_id' => null,
                        'tipo_movimiento' => 'Salida',
                        'cantidad' => $cantidad,
                        'fecha' => now(),
                        'user_id' => auth()->id(),
                        'vehiculo_vin' => $data['vehiculo_vin'],
                    ]);
                }

                return $asignacion;
            }, 3);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'No se pudo asignar el vehículo.');
        }

        try {
            app(NotificacionService::class)->notificarNuevaAsignacion(
                $asignacion,
                $request->user(),
                route('admin.vehiculos.asignaciones.index')
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()
            ->route('admin.vehiculos.asignaciones.index')
            ->with('success', 'Asignación de vehículo creada correctamente.')
            ->with('pdf_url', route('admin.vehiculos.asignaciones.pdf_asignacion', $asignacion));
    }

    public function desasignar(Request $request, AsignacionVehiculo $asignacion, InventarioStockService $stock)
    {
        $data = $request->validate([
            'fecha_fin' => ['required', 'date'],
            'estado_final_vehiculo' => ['required', 'string', 'max:60'],
            'observaciones_desasignacion' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
            'productos.*.id' => ['required_with:productos', 'integer', 'distinct'],
            'productos.*.accion' => ['required_with:productos', 'in:consumido,regresar,danado'],
            'productos.*.cantidad' => ['required_with:productos', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data, $asignacion, $stock) {
            Vehiculo::whereKey($asignacion->vehiculo_vin)->lockForUpdate()->firstOrFail();
            $asignacion = AsignacionVehiculo::whereKey($asignacion->id)->lockForUpdate()->firstOrFail();
            if (!$asignacion->activa) {
                throw ValidationException::withMessages(['fecha_fin' => 'La asignación ya está cerrada.']);
            }
            if (\Illuminate\Support\Carbon::parse($data['fecha_fin'])->lt($asignacion->fecha_inicio->startOfDay())) {
                throw ValidationException::withMessages(['fecha_fin' => 'La fecha final es anterior a la asignación.']);
            }

            $asignacion->update([
                'fecha_fin' => $data['fecha_fin'],
                'estado_final_vehiculo' => $data['estado_final_vehiculo'],
                'observaciones_desasignacion' => $data['observaciones_desasignacion'] ?? null,
                'desasignado_por_user_id' => auth()->id(),
                'activa' => false,
            ]);

            Vehiculo::where('vin', $asignacion->vehiculo_vin)
                ->update(['estado' => 'Disponible']);

            $items = collect($data['productos'] ?? [])->keyBy('id');
            $refacciones = VehiculoProductoAsignacion::where('asignacion_vehiculo_id', $asignacion->id)
                ->where('vehiculo_vin', $asignacion->vehiculo_vin)
                ->where('activa', true)->whereKey($items->keys()->all())
                ->orderBy('producto_codigo')->orderBy('id')->lockForUpdate()->get();

            if ($refacciones->count() !== $items->count()) {
                throw ValidationException::withMessages(['productos' => 'Una refacción no pertenece a esta asignación o ya fue cerrada.']);
            }

            foreach ($refacciones as $refaccion) {
                $item = $items->get($refaccion->id);
                $cantidad = (int) $item['cantidad'];
                $accion = $item['accion'];
                if ($cantidad > (int) $refaccion->cantidad) {
                    throw ValidationException::withMessages(['productos' => 'La cantidad supera el saldo activo de la refacción.']);
                }

                $stock->cerrarDeVehiculo($refaccion, $cantidad, $accion);
                if ($accion === 'regresar') {
                    Movimiento::create([
                        'producto_codigo' => $refaccion->producto_codigo,
                        'bodega_destino_id' => $refaccion->bodega_id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => $cantidad,
                        'fecha' => now(),
                        'user_id' => auth()->id(),
                        'vehiculo_vin' => $asignacion->vehiculo_vin,
                    ]);
                }

                $cerrada = $refaccion;
                if ($cantidad < (int) $refaccion->cantidad) {
                    $cerrada = $refaccion->replicate();
                    $refaccion->decrement('cantidad', $cantidad);
                }
                $cerrada->fill([
                    'cantidad' => $cantidad,
                    'estado' => $accion === 'regresar' ? 'regresado' : $accion,
                    'activa' => false,
                    'fecha_cierre' => now(),
                    'accion_cierre' => $accion,
                    'cerrado_por_user_id' => auth()->id(),
                ])->save();
            }
        }, 3);

        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarCambioEstadoAsignacion(
                [$asignacion->fresh()],
                $request->user(),
                'una desasignación de vehículo'
            )
        );

        return redirect()
            ->route('admin.vehiculos.asignaciones.index')
            ->with('success', 'Vehículo desasignado correctamente.');
    }

    public function pdfAsignacion(AsignacionVehiculo $asignacion)
    {
        $asignacion->load(['vehiculo', 'colaborador', 'productos.producto']);

        return view('admin.vehiculos.asignaciones.pdf_asignacion', compact('asignacion'));
    }

    public function pdfDesasignacion(AsignacionVehiculo $asignacion)
    {
        $asignacion->load(['vehiculo', 'colaborador', 'productos.producto']);

        return view('admin.vehiculos.asignaciones.pdf_desasignacion', compact('asignacion'));
    }

    public function subirPdfFirmado(Request $request, AsignacionVehiculo $asignacion)
    {
        $data = $request->validate([
            'tipo_documento' => ['required', 'in:asignacion_firmada,devolucion_firmada'],
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($data['tipo_documento'] === 'devolucion_firmada' && $asignacion->activa) {
            return back()->with('error', 'No puedes subir PDF de devolución si la asignación aún está activa.');
        }

        $file = $request->file('archivo');

        $path = DocumentoPrivado::guardar($file, 'vehiculos/asignaciones/' . $asignacion->id);
        try {
            AsignacionVehiculoArchivo::create([
                'asignacion_vehiculo_id' => $asignacion->id,
                'tipo_documento' => $data['tipo_documento'],
                'ruta' => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'tamano' => $file->getSize(),
                'subido_por_user_id' => auth()->id(),
            ]);
        } catch (Throwable $e) {
            DocumentoPrivado::descartar($path);
            throw $e;
        }

        return back()->with('success', 'PDF firmado subido correctamente.');
    }

    public function verPdfFirmado(AsignacionVehiculoArchivo $archivo)
    {
        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }
}
