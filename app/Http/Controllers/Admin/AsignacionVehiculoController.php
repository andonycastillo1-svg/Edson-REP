<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionVehiculo;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Inventario;
use App\Models\Movimiento;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionVehiculoController extends Controller
{
    public function index()
    {
        $asignaciones = AsignacionVehiculo::with(['vehiculo', 'colaborador', 'productos.producto'])
            ->latest('fecha_inicio')->paginate(20);
        return view('admin.vehiculos.asignaciones.index', compact('asignaciones'));
    }

    public function create()
    {
        $vehiculos = Vehiculo::orderBy('placa')->get();
        $colaboradores = Colaborador::where('estado', 'Activo')->orderBy('nombre')->get();
        $inventarios = Inventario::with(['producto', 'bodega'])->where('cantidad', '>', 0)->get();
        $bodegas = Bodega::orderBy('nombre')->get();

        return view('admin.vehiculos.asignaciones.create', compact('vehiculos', 'colaboradores', 'inventarios', 'bodegas'));
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
            'productos.*.tipo_control' => ['required_with:productos', 'in:unidad,cantidad'],
            'productos.*.serial' => ['nullable', 'string', 'max:120'],
            'productos.*.destino' => ['nullable', 'in:asignado'],
            'productos.*.observaciones' => ['nullable', 'string'],
        ]);

        $yaAsignado = AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])->where('activa', true)->exists();
        if ($yaAsignado) return back()->withInput()->with('error', 'El vehículo ya tiene una asignación activa.');

        $personaConVehiculo = AsignacionVehiculo::where('colaborador_codigo', $data['colaborador_codigo'])->where('activa', true)->exists();
        if ($personaConVehiculo) return back()->withInput()->with('error', 'La persona ya tiene un vehículo activo asignado.');

        DB::transaction(function () use ($data) {
            $asignacion = AsignacionVehiculo::create([
                'vehiculo_vin' => $data['vehiculo_vin'],
                'colaborador_codigo' => $data['colaborador_codigo'],
                'asignado_por_user_id' => auth()->id(),
                'fecha_inicio' => $data['fecha_inicio'],
                'estado_inicial_vehiculo' => $data['estado_inicial_vehiculo'],
                'observaciones_asignacion' => $data['observaciones_asignacion'] ?? null,
                'activa' => true,
            ]);

            Vehiculo::where('vin', $data['vehiculo_vin'])->update(['estado' => 'En uso']);

            foreach (($data['productos'] ?? []) as $prod) {
                $inv = Inventario::where('producto_codigo', $prod['producto_codigo'])->where('bodega_id', $prod['bodega_id'])->lockForUpdate()->first();
                if (!$inv || (int)$inv->cantidad < (int)$prod['cantidad']) throw new \RuntimeException('Stock insuficiente para producto a vehículo.');

                if (($prod['tipo_control'] ?? 'cantidad') === 'unidad' && !empty($prod['serial'])) {
                    $serialUsado = VehiculoProductoAsignacion::where('producto_codigo', $prod['producto_codigo'])->where('serial', $prod['serial'])->where('activa', true)->exists();
                    if ($serialUsado) throw new \RuntimeException('Serial ya asignado activamente.');
                }

                $inv->decrement('cantidad', (int)$prod['cantidad']);

                VehiculoProductoAsignacion::create([
                    'asignacion_vehiculo_id' => $asignacion->id,
                    'vehiculo_vin' => $data['vehiculo_vin'],
                    'producto_codigo' => $prod['producto_codigo'],
                    'bodega_id' => $prod['bodega_id'],
                    'cantidad' => (int)$prod['cantidad'],
                    'tipo_control' => $prod['tipo_control'],
                    'serial' => $prod['serial'] ?? null,
                    'fecha' => $data['fecha_inicio'],
                    'asignado_por_user_id' => auth()->id(),
                    'observaciones' => $prod['observaciones'] ?? null,
                    'activa' => true,
                ]);

                Movimiento::create([
                    'producto_codigo' => $prod['producto_codigo'],
                    'bodega_origen_id' => $prod['bodega_id'],
                    'bodega_destino_id' => null,
                    'tipo_movimiento' => 'Salida',
                    'cantidad' => (int)$prod['cantidad'],
                    'fecha' => now(),
                    'user_id' => auth()->id(),
                    'vehiculo_vin' => $data['vehiculo_vin'],
                ]);
            }
        });

        return redirect()->route('admin.vehiculos.asignaciones.index')->with('success', 'Asignación de vehículo creada correctamente.');
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

        if (!$asignacion->activa) return back()->with('error', 'La asignación ya está cerrada.');

        DB::transaction(function () use ($data, $asignacion) {
            $asignacion->update([
                'fecha_fin' => $data['fecha_fin'],
                'estado_final_vehiculo' => $data['estado_final_vehiculo'],
                'observaciones_desasignacion' => $data['observaciones_desasignacion'] ?? null,
                'desasignado_por_user_id' => auth()->id(),
                'activa' => false,
            ]);

            Vehiculo::where('vin', $asignacion->vehiculo_vin)->update(['estado' => 'Disponible']);

            foreach (($data['productos'] ?? []) as $item) {
                $rel = VehiculoProductoAsignacion::lockForUpdate()->findOrFail($item['id']);
                $cantidad = min((int)$item['cantidad'], (int)$rel->cantidad);
                $accion = $item['accion'];

                if ($accion === 'regresar') {
                    $inv = Inventario::firstOrCreate(
                        ['producto_codigo' => $rel->producto_codigo, 'bodega_id' => $rel->bodega_id],
                        ['cantidad' => 0]
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

        return redirect()->route('admin.vehiculos.asignaciones.index')->with('success', 'Vehículo desasignado correctamente.');
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
}
