<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Colaborador;
use App\Models\Bodega;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AsignacionInventarioController extends Controller
{
    public function index()
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        $asignaciones = AsignacionInventario::with(['colaborador', 'producto', 'bodega'])
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('admin.asignaciones.index', compact('asignaciones', 'routePrefix'));
    }

    public function create()
    {
        $inventarios = Inventario::with('producto', 'bodega')->get();
        $colaboradores = Colaborador::where('estado', 'Activo')->get();
        $bodegas = Bodega::all();

        $aprobadores = [
            'Gerencia',
            'RRHH',
            'Jefe de área',
            'Supervisor'
        ];

        return view('admin.asignaciones.create', compact(
            'inventarios',
            'colaboradores',
            'bodegas',
            'aprobadores'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'producto_codigo' => 'required|exists:productos,codigo',
            'colaborador_codigo' => 'required|exists:colaboradores,codigo',
            'bodega_id' => 'required|exists:bodegas,id',
            'cantidad_asignada' => 'required|integer|min:1',
            'fecha' => 'required|date',
            'costo_unitario' => 'nullable|numeric',
            'aprobado_por' => 'required|string',
            'medio_solicitud' => 'required|in:WhatsApp,Correo',
            'imagen' => 'nullable|image',
            'observaciones' => 'nullable|string'
        ]);

        // Buscar inventario
        $inventario = Inventario::where('producto_codigo', $data['producto_codigo'])
            ->where('bodega_id', $data['bodega_id'])
            ->first();

        if (!$inventario || $inventario->cantidad < $data['cantidad_asignada']) {
            return back()->with('error', 'Stock insuficiente');
        }

        // Si no viene costo, tomarlo del producto
        if (empty($data['costo_unitario'])) {
            $data['costo_unitario'] = $inventario->producto->costo ?? 0;
        }

        // Descontar stock
        $inventario->decrement('cantidad', $data['cantidad_asignada']);

        // Vida útil
        $producto = $inventario->producto;

        if ($producto->vida_util_meses) {
            $data['fecha_vencimiento'] = now()->addMonths($producto->vida_util_meses);
        }

        // Imagen
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('asignaciones', 'public');
        }

        // Guardar
        $asignacion = AsignacionInventario::create($data + [
            'user_id' => auth()->id(),
        ]);

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.asignaciones.pdf', $asignacion->colaborador_codigo)
            ->route($routePrefix . '.asignaciones.pdf', $data['colaborador_codigo'])
            ->with('success', 'Asignación realizada correctamente');
    }

    // 🔥 NUEVO: GENERAR HOJA PDF / IMPRIMIBLE
    public function pdf($codigo)
    {
        $colaborador = Colaborador::where('codigo', $codigo)->firstOrFail();
        $usuario = auth()->user();

        $asignaciones = AsignacionInventario::with('producto', 'bodega')
            ->where('colaborador_codigo', $codigo)
            ->get();

        $total = $asignaciones->sum(function ($a) {
            return ($a->costo_unitario ?? 0) * $a->cantidad_asignada;
        });

        $asignadorNombre = $usuario?->name ?? 'No identificado';
        $bodegaAsignador = $usuario?->bodega?->nombre
            ?? optional($asignaciones->first()?->bodega)->nombre
            ?? 'No definida';

        return view('admin.asignaciones.pdf', compact(
            'colaborador',
            'asignaciones',
            'total',
            'asignadorNombre',
            'bodegaAsignador'
        ));
    }

    public function uploadPdfFirmado(Request $request, AsignacionInventario $asignacion)
    {
        if ((int) $asignacion->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'pdf_firmado' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($asignacion->pdf_firmado) {
            Storage::disk('public')->delete($asignacion->pdf_firmado);
        }

        $path = $data['pdf_firmado']->store('asignaciones/firmados', 'public');
        $asignacion->update(['pdf_firmado' => $path]);

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Documento firmado cargado correctamente.');
    }
}
