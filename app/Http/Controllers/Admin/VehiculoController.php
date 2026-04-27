<?php

namespace App\Http\Controllers\Admin;

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

        Vehiculo::create($data);

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
            'placa' => 'required|max:20|unique:vehiculos,placa,' . $vin . ',vin',
            'marca' => 'nullable|max:50',
            'modelo' => 'nullable|max:50',
            'estado' => 'required|in:Disponible,En uso,Mantenimiento'
        ]);

        $vehiculo->update($data);

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
