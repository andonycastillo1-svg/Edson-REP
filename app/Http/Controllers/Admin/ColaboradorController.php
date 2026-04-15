<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\AsignacionInventario;
use Illuminate\Http\Request;

class ColaboradorController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $activos = Colaborador::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('codigo', 'like', "%{$q}%")
                      ->orWhere('nombre', 'like', "%{$q}%")
                      ->orWhere('puesto', 'like', "%{$q}%");
                });
            })
            ->where('estado', 'Activo')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $inactivos = Colaborador::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('codigo', 'like', "%{$q}%")
                      ->orWhere('nombre', 'like', "%{$q}%")
                      ->orWhere('puesto', 'like', "%{$q}%");
                });
            })
            ->where('estado', 'Inactivo')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('admin.colaboradores.index', compact('activos', 'inactivos'));
    }

    public function create()
    {
        return view('admin.colaboradores.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        Colaborador::create($data);

        return redirect()
            ->route('admin.colaboradores.index')
            ->with('success', 'Colaborador creado correctamente.');
    }

    public function edit(Colaborador $colaborador)
    {
        return view('admin.colaboradores.edit', compact('colaborador'));
    }

    public function update(Request $request, Colaborador $colaborador)
    {
        $data = $request->validate([
            'codigo' => ['required', 'unique:colaboradores,codigo,' . $colaborador->codigo . ',codigo'],
            'nombre' => ['required', 'max:255'],
            'puesto' => ['required', 'max:255'],
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        $colaborador->update($data);

        return redirect()
            ->route('admin.colaboradores.index')
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    public function detalle(Colaborador $colaborador)
    {
        $asignaciones = AsignacionInventario::with('producto', 'bodega')
            ->where('colaborador_codigo', $colaborador->codigo)
            ->get();

        $totalGeneral = 0;

        $dataAsignaciones = $asignaciones->map(function ($a) use (&$totalGeneral) {

            $costoUnitario = $a->costo_unitario ?? 0;
            $total = $costoUnitario * $a->cantidad_asignada;

            $totalGeneral += $total;

            return [
                'producto' => $a->producto->nombre ?? '—',
                'bodega' => $a->bodega->nombre ?? '—',
                'cantidad' => $a->cantidad_asignada,
                'costo_unitario' => $costoUnitario,
                'total' => $total,
                'fecha' => $a->fecha,
            ];
        });

        return response()->json([
            'colaborador' => [
                'codigo' => $colaborador->codigo,
                'nombre' => $colaborador->nombre,
                'puesto' => $colaborador->puesto,
                'estado' => $colaborador->estado,
            ],
            'asignaciones' => $dataAsignaciones,
            'total_general' => $totalGeneral
        ]);
    }

    public function cambiarEstado(Colaborador $colaborador)
    {
        $nuevoEstado = $colaborador->estado === 'Activo' ? 'Inactivo' : 'Activo';

        $colaborador->update([
            'estado' => $nuevoEstado,
        ]);

        return redirect()
            ->route('admin.colaboradores.index')
            ->with('success', 'Estado del colaborador actualizado correctamente.');
    }
}