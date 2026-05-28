<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionVehiculo;
use App\Models\Colaborador;
use App\Models\Vehiculo;
use App\Models\VehiculoProductoAsignacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignacionVehiculoController extends Controller
{
    public function index()
    {
        $asignaciones = AsignacionVehiculo::with(['vehiculo', 'colaborador'])
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

        return view('admin.vehiculos.asignaciones.create', compact('vehiculos', 'colaboradores'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehiculo_vin' => ['required', 'exists:vehiculos,vin'],
            'colaborador_codigo' => ['required', 'exists:colaboradores,codigo'],
            'fecha_inicio' => ['required', 'date'],
            'estado_inicial_vehiculo' => ['required', 'string', 'max:60'],
            'observaciones_asignacion' => ['nullable', 'string'],
        ]);

        $yaAsignado = AsignacionVehiculo::where('vehiculo_vin', $data['vehiculo_vin'])
            ->where('activa', true)
            ->exists();

        if ($yaAsignado) {
            return back()->withInput()->with('error', 'El vehículo ya tiene una asignación activa.');
        }

        $personaConVehiculo = AsignacionVehiculo::where('colaborador_codigo', $data['colaborador_codigo'])
            ->where('activa', true)
            ->exists();

        if ($personaConVehiculo) {
            return back()->withInput()->with('error', 'La persona ya tiene un vehículo activo asignado.');
        }

        DB::transaction(function () use ($data) {
            AsignacionVehiculo::create([
                'vehiculo_vin' => $data['vehiculo_vin'],
                'colaborador_codigo' => $data['colaborador_codigo'],
                'asignado_por_user_id' => auth()->id(),
                'fecha_inicio' => $data['fecha_inicio'],
                'estado_inicial_vehiculo' => $data['estado_inicial_vehiculo'],
                'observaciones_asignacion' => $data['observaciones_asignacion'] ?? null,
                'activa' => true,
            ]);

            Vehiculo::where('vin', $data['vehiculo_vin'])->update(['estado' => 'En uso']);
        });

        return redirect()
            ->route('admin.vehiculos.asignaciones.index')
            ->with('success', 'Asignación de vehículo creada correctamente.');
    }

    public function desasignar(Request $request, AsignacionVehiculo $asignacion)
    {
        $data = $request->validate([
            'fecha_fin' => ['required', 'date'],
            'estado_final_vehiculo' => ['required', 'string', 'max:60'],
            'observaciones_desasignacion' => ['nullable', 'string'],
        ]);

        if (!$asignacion->activa) {
            return back()->with('error', 'La asignación ya está cerrada.');
        }

        $productosActivos = VehiculoProductoAsignacion::where('vehiculo_vin', $asignacion->vehiculo_vin)
            ->where('activa', true)
            ->count();

        DB::transaction(function () use ($data, $asignacion) {
            $asignacion->update([
                'fecha_fin' => $data['fecha_fin'],
                'estado_final_vehiculo' => $data['estado_final_vehiculo'],
                'observaciones_desasignacion' => $data['observaciones_desasignacion'] ?? null,
                'desasignado_por_user_id' => auth()->id(),
                'activa' => false,
            ]);

            Vehiculo::where('vin', $asignacion->vehiculo_vin)->update(['estado' => 'Disponible']);
        });

        $mensaje = 'Vehículo desasignado correctamente.';

        if ($productosActivos > 0) {
            $mensaje .= ' Advertencia: el vehículo conserva ' . $productosActivos . ' producto(s)/refacción(es) activo(s); no se cerraron automáticamente.';
        }

        return redirect()
            ->route('admin.vehiculos.asignaciones.index')
            ->with('success', $mensaje);
    }

    public function pdfAsignacion(AsignacionVehiculo $asignacion)
    {
        $asignacion->load(['vehiculo', 'colaborador']);
        return view('admin.vehiculos.asignaciones.pdf_asignacion', compact('asignacion'));
    }

    public function pdfDesasignacion(AsignacionVehiculo $asignacion)
    {
        $asignacion->load(['vehiculo', 'colaborador']);
        return view('admin.vehiculos.asignaciones.pdf_desasignacion', compact('asignacion'));
    }
}
