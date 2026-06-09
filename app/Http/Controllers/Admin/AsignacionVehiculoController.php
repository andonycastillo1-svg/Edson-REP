<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Storage;
use Throwable;

class AsignacionVehiculoController extends Controller
{
    public function index()
    {
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
            ->where('cantidad', '>', 0)
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehiculo_vin' => ['required', 'exists:vehiculos,vin'],
            'colaborador_codigo' => ['required', 'exists:colaboradores,codigo'],
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

        $yaAsignado = AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])
            ->where('activa', true)
            ->exists();

        if ($yaAsignado) {
            return back()
                ->withInput()
                ->with('error', 'El vehículo ya tiene una asignación activa.');
        }

        $personaConVehiculo = AsignacionVehiculo::where('colaborador_codigo', $data['colaborador_codigo'])
            ->where('activa', true)
            ->exists();

        if ($personaConVehiculo) {
            return back()
                ->withInput()
                ->with('error', 'La persona ya tiene un vehículo activo asignado.');
        }

        try {
            $asignacion = DB::transaction(function () use ($data) {
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

                foreach (($data['productos'] ?? []) as $prod) {
                    $productoCodigo = $prod['producto_codigo'];
                    $bodegaId = $prod['bodega_id'];
                    $cantidad = (int) $prod['cantidad'];

                    $inv = Inventario::with('producto')
                        ->where('producto_codigo', $productoCodigo)
                        ->where('bodega_id', $bodegaId)
                        ->lockForUpdate()
                        ->first();

                    if (!$inv) {
                        throw new \RuntimeException('No existe inventario para el producto y bodega seleccionados.');
                    }

                    $categoriaProducto = strtolower(trim(optional($inv->producto)->categoria ?? ''));

                    if ($categoriaProducto !== 'refacciones') {
                        throw new \RuntimeException('Solo se pueden asignar productos con categoría refacciones.');
                    }

                    if ((int) $inv->cantidad < $cantidad) {
                        throw new \RuntimeException('Stock insuficiente para uno de los productos seleccionados.');
                    }

                    $inv->decrement('cantidad', $cantidad);

                    VehiculoProductoAsignacion::create([
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
            });
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.vehiculos.asignaciones.index')
            ->with('success', 'Asignación de vehículo creada correctamente.')
            ->with('pdf_url', route('admin.vehiculos.asignaciones.pdf_asignacion', $asignacion));
    }

    public function desasignar(Request $request, AsignacionVehiculo $asignacion)
    {
        $data = $request->validate([
            'fecha_fin' => ['required', 'date'],
            'estado_final_vehiculo' => ['required', 'string', 'max:60'],
            'observaciones_desasignacion' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
            'productos.*.id' => ['required_with:productos', 'exists:vehiculo_producto_asignaciones,id'],
            'productos.*.accion' => ['required_with:productos', 'in:consumido,regresar,danado'],
            'productos.*.cantidad' => ['required_with:productos', 'integer', 'min:1'],
        ]);

        if (!$asignacion->activa) {
            return back()->with('error', 'La asignación ya está cerrada.');
        }

        DB::transaction(function () use ($data, $asignacion) {
            $asignacion->update([
                'fecha_fin' => $data['fecha_fin'],
                'estado_final_vehiculo' => $data['estado_final_vehiculo'],
                'observaciones_desasignacion' => $data['observaciones_desasignacion'] ?? null,
                'desasignado_por_user_id' => auth()->id(),
                'activa' => false,
            ]);

            Vehiculo::where('vin', $asignacion->vehiculo_vin)
                ->update(['estado' => 'Disponible']);

            foreach (($data['productos'] ?? []) as $item) {
                $rel = VehiculoProductoAsignacion::lockForUpdate()->findOrFail($item['id']);
                $cantidad = min((int) $item['cantidad'], (int) $rel->cantidad);
                $accion = $item['accion'];

                if ($accion === 'regresar') {
                    $inv = Inventario::firstOrCreate(
                        [
                            'producto_codigo' => $rel->producto_codigo,
                            'bodega_id' => $rel->bodega_id,
                        ],
                        [
                            'cantidad' => 0,
                        ]
                    );

                    $inv->increment('cantidad', $cantidad);

                    Movimiento::create([
                        'producto_codigo' => $rel->producto_codigo,
                        'bodega_origen_id' => null,
                        'bodega_destino_id' => $rel->bodega_id,
                        'tipo_movimiento' => 'Entrada',
                        'cantidad' => $cantidad,
                        'fecha' => now(),
                        'user_id' => auth()->id(),
                        'vehiculo_vin' => $asignacion->vehiculo_vin,
                    ]);
                }

                $rel->activa = false;
                $rel->observaciones = trim(($rel->observaciones ? $rel->observaciones . ' | ' : '') . 'Cierre: ' . $accion);
                $rel->save();
            }
        });

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

        $path = $file->store(
            'vehiculos/asignaciones/' . $asignacion->id,
            'public'
        );

        $anterior = AsignacionVehiculoArchivo::where('asignacion_vehiculo_id', $asignacion->id)
            ->where('tipo_documento', $data['tipo_documento'])
            ->latest()
            ->first();

        if ($anterior && Storage::disk('public')->exists($anterior->ruta)) {
            Storage::disk('public')->delete($anterior->ruta);
        }

        if ($anterior) {
            $anterior->delete();
        }

        AsignacionVehiculoArchivo::create([
            'asignacion_vehiculo_id' => $asignacion->id,
            'tipo_documento' => $data['tipo_documento'],
            'ruta' => $path,
            'nombre_original' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'tamano' => $file->getSize(),
            'subido_por_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'PDF firmado subido correctamente.');
    }

    public function verPdfFirmado(AsignacionVehiculoArchivo $archivo)
    {
        if (!Storage::disk('public')->exists($archivo->ruta)) {
            abort(404, 'El archivo no existe.');
        }

        return response()->file(
            Storage::disk('public')->path($archivo->ruta),
            [
                'Content-Type' => $archivo->mime ?? 'application/pdf',
            ]
        );
    }
}