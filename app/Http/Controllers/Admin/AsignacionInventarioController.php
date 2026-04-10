<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInventario;
use App\Models\Inventario;
use App\Models\Colaborador;
use App\Models\Bodega;
use App\Models\AsignacionMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AsignacionInventarioController extends Controller
{
    public function index()
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        $query = AsignacionInventario::with(['colaborador', 'producto', 'bodega'])->latest();

        if (Schema::hasColumn('asignaciones_inventarios', 'user_id')) {
            $query->where('user_id', auth()->id());
        }

        $asignaciones = $query->paginate(15);
        $movimientos = collect();
        if (Schema::hasTable('asignacion_movimientos')) {
            $movimientos = AsignacionMovimiento::with(['asignacion.colaborador', 'user'])
                ->latest()
                ->limit(30)
                ->get();
        }

        return view('admin.asignaciones.index', compact('asignaciones', 'routePrefix', 'movimientos'));
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

        // Control por stock: un mismo código puede asignarse a varios colaboradores
        // siempre que exista cantidad disponible en inventario.
        if (!$inventario || $inventario->cantidad < $data['cantidad_asignada']) {
            return back()->with('error', 'Stock insuficiente');
        }

        // Si no viene costo, tomarlo del producto
        if (empty($data['costo_unitario'])) {
            $ultimoCosto = DB::table('compra_detalles as cd')
                ->join('compras as c', 'c.id', '=', 'cd.compra_id')
                ->where('cd.producto_codigo', $data['producto_codigo'])
                ->orderByDesc('c.fecha_compra')
                ->orderByDesc('cd.id')
                ->value('cd.precio_unitario');

            $data['costo_unitario'] = $ultimoCosto ?? $inventario->producto->costo ?? 0;
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
        $payload = $data;
        if (Schema::hasColumn('asignaciones_inventarios', 'user_id')) {
            $payload['user_id'] = auth()->id();
        }

        $asignacion = AsignacionInventario::create($payload);

        if (Schema::hasTable('asignacion_movimientos')) {
            AsignacionMovimiento::create([
                'asignacion_inventario_id' => $asignacion->id,
                'tipo' => 'Asignacion',
                'cantidad' => $asignacion->cantidad_asignada,
                'detalle' => 'Asignación inicial del producto.',
                'user_id' => auth()->id(),
            ]);
        }

        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        return redirect()
            ->route($routePrefix . '.asignaciones.pdf', $asignacion->colaborador_codigo)
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
        if (Schema::hasColumn('asignaciones_inventarios', 'user_id')
            && (int) $asignacion->user_id !== (int) auth()->id()) {
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

    public function devolver(Request $request, AsignacionInventario $asignacion)
    {
        $routePrefix = auth()->user()->role_id == 2 ? 'operador' : 'admin';

        if ((int) auth()->user()->role_id !== 1 && (int) $asignacion->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($asignacion->estado !== 'Activa') {
            return redirect()->route($routePrefix . '.asignaciones.index')
                ->with('error', 'La asignación ya no está activa.');
        }

        $data = $request->validate([
            'cantidad_devuelta' => ['required', 'integer', 'min:1'],
            'detalle_devolucion' => ['nullable', 'string', 'max:1000'],
        ]);

        $cantidadDevuelta = min((int) $data['cantidad_devuelta'], (int) $asignacion->cantidad_asignada);

        DB::transaction(function () use ($asignacion, $cantidadDevuelta, $data) {
            $asignacion->cantidad_asignada = (int) $asignacion->cantidad_asignada - $cantidadDevuelta;
            if ((int) $asignacion->cantidad_asignada <= 0) {
                $asignacion->cantidad_asignada = 0;
                $asignacion->estado = 'Devuelta';
            }
            $asignacion->save();

            Inventario::query()
                ->where('bodega_id', $asignacion->bodega_id)
                ->where('producto_codigo', $asignacion->producto_codigo)
                ->increment('cantidad', $cantidadDevuelta);

            if (Schema::hasTable('asignacion_movimientos')) {
                AsignacionMovimiento::create([
                    'asignacion_inventario_id' => $asignacion->id,
                    'tipo' => 'Devolucion',
                    'cantidad' => $cantidadDevuelta,
                    'detalle' => $data['detalle_devolucion'] ?? 'Devolución de producto.',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return redirect()->route($routePrefix . '.asignaciones.index')
            ->with('success', 'Devolución registrada correctamente.');
    }
}
