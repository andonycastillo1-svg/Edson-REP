<?php

namespace App\Http\Controllers\Admin;

use App\Sistema\ValoresCampos;
use App\Sistema\Presentacion;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehiculo;

class VehiculoController extends Controller
{

    public function index()
    {
        $vehiculos = Vehiculo::orderBy('marca')->paginate(10);

        return view('admin.vehiculos.index', compact('vehiculos'));
    }


    public function create()
    {
        return view('admin.vehiculos.create');
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'vin' => 'required|max:50|unique:vehiculos,vin',
            'placa' => 'required|max:20|unique:vehiculos,placa',
            'marca' => 'nullable|max:50',
            'modelo' => 'nullable|max:50',
            'estado' => 'required|in:Disponible,En uso,Mantenimiento'
        ]);

        app(Presentacion::class)->validar($request, 'vehiculos', true);
        $campos = app(ValoresCampos::class);
        $valores = $campos->preparar($request, 'vehiculos', true);
        DB::transaction(function () use ($data, $campos, $valores) {
            $campos->verificarClaveDisponible('vehiculos', $data['vin']);
            $vehiculo = Vehiculo::create($data);
            $campos->guardar('vehiculos', $vehiculo->vin, $valores);
        });

        return redirect()
            ->route('admin.vehiculos.index')
            ->with('success','Vehículo creado correctamente');
    }


    public function show($vin)
    {
        $vehiculo = Vehiculo::findOrFail($vin);

        return view('admin.vehiculos.show', compact('vehiculo'));
    }


    public function edit($vin)
    {
        $vehiculo = Vehiculo::findOrFail($vin);

        return view('admin.vehiculos.edit', compact('vehiculo'));
    }


    public function update(Request $request, $vin)
    {
        $vehiculo = Vehiculo::findOrFail($vin);

        $data = $request->validate([
            'placa' => ['required', 'max:20', \Illuminate\Validation\Rule::unique('vehiculos', 'placa')->ignore($vehiculo->vin, 'vin')],
            'marca' => 'nullable|max:50',
            'modelo' => 'nullable|max:50',
            'estado' => 'required|in:Disponible,En uso,Mantenimiento'
        ]);

        app(Presentacion::class)->validar($request, 'vehiculos', false);
        $campos = app(ValoresCampos::class);
        DB::transaction(function () use ($request, $vehiculo, $data, $campos) {
            $vehiculo = Vehiculo::whereKey($vehiculo->vin)->lockForUpdate()->firstOrFail();
            $valores = $campos->preparar($request, 'vehiculos', false, $vehiculo->vin);
            $vehiculo->update($data);
            $campos->guardar('vehiculos', $vehiculo->vin, $valores);
        });

        return redirect()
            ->route('admin.vehiculos.index')
            ->with('success','Vehículo actualizado');
    }


    public function destroy($vin)
    {
        $vehiculo = Vehiculo::findOrFail($vin);

        $vehiculo->delete();

        return redirect()
            ->route('admin.vehiculos.index')
            ->with('success','Vehículo eliminado');
    }
}
