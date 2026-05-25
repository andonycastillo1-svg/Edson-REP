<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlertaReemplazo;
use App\Models\Colaborador;
use App\Models\AsignacionInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
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
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    public function detalle(Colaborador $colaborador)
    {
        $ficha = $this->generarFichaColaborador($colaborador);

        return response()->json([
            'colaborador' => $ficha['colaborador'],
            'asignaciones' => $ficha['asignaciones'],
            'total_general' => $ficha['total_general'],
            'cobros' => $ficha['cobros'],
            'total_cobros' => $ficha['total_cobros'],
        ]);
    }

    public function fichaTecnica(Colaborador $colaborador)
    {
        $ficha = $this->generarFichaColaborador($colaborador);

        $filename = 'ficha_tecnica_' . $colaborador->codigo . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($ficha) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['FICHA TÉCNICA DEL COLABORADOR']);
            fputcsv($out, []);

            fputcsv($out, ['Código', $ficha['colaborador']['codigo']]);
            fputcsv($out, ['Nombre', $ficha['colaborador']['nombre']]);
            fputcsv($out, ['Puesto', $ficha['colaborador']['puesto']]);
            fputcsv($out, ['Estado', $ficha['colaborador']['estado']]);
            fputcsv($out, ['Fecha de descarga', now()->format('d/m/Y H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['INVENTARIO / EQUIPO ACTUALMENTE ASIGNADO']);
            fputcsv($out, [
                'Código producto',
                'Producto',
                'Bodega',
                'Cantidad',
                'Costo unitario',
                'Total',
                'Fecha asignación',
                'Fecha vencimiento',
                'Estado vida útil',
            ]);

            if (count($ficha['asignaciones']) === 0) {
                fputcsv($out, ['Sin asignaciones activas']);
            } else {
                foreach ($ficha['asignaciones'] as $item) {
                    fputcsv($out, [
                        $item['producto_codigo'],
                        $item['producto'],
                        $item['bodega'],
                        $item['cantidad'],
                        number_format((float) $item['costo_unitario'], 2, '.', ''),
                        number_format((float) $item['total'], 2, '.', ''),
                        $item['fecha_asignacion'] ?? '—',
                        $item['fecha_vencimiento'] ?? '—',
                        $item['estado_vida_util'],
                    ]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['Total inventario asignado', number_format((float) $ficha['total_general'], 2, '.', '')]);
            fputcsv($out, []);

            fputcsv($out, ['COBROS / DESCUENTOS POR DAÑO O REEMPLAZO ANTES DE VIDA ÚTIL']);
            fputcsv($out, [
                'Código producto',
                'Producto',
                'Fecha asignación anterior',
                'Fecha daño/reemplazo',
                'Vida útil meses',
                'Meses restantes',
                'Costo producto',
                'Aplica cobro',
                'Monto cobro',
                'Estado',
                'Detalle',
            ]);

            if (count($ficha['cobros']) === 0) {
                fputcsv($out, ['Sin cobros o descuentos registrados']);
            } else {
                foreach ($ficha['cobros'] as $cobro) {
                    fputcsv($out, [
                        $cobro['producto_codigo'],
                        $cobro['producto'],
                        $cobro['fecha_asignacion_anterior'],
                        $cobro['fecha_dano_reemplazo'],
                        $cobro['vida_util_meses'],
                        $cobro['meses_restantes'],
                        number_format((float) $cobro['costo_producto'], 2, '.', ''),
                        $cobro['descuento_aplicable'] ? 'Sí' : 'No',
                        number_format((float) $cobro['monto_cobro'], 2, '.', ''),
                        $cobro['estado'],
                        $cobro['detalle'],
                    ]);
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['Total cobros/descuentos', number_format((float) $ficha['total_cobros'], 2, '.', '')]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function generarFichaColaborador(Colaborador $colaborador): array
    {
        $asignaciones = AsignacionInventario::with('producto', 'bodega')
            ->where('colaborador_codigo', $colaborador->codigo)
            ->where('cantidad_asignada', '>', 0)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhere('estado', 'Activa');
            })
            ->get();

        $codigosProducto = $asignaciones->pluck('producto_codigo')->filter()->unique()->values();
        $ultimosCostos = collect();

        if ($codigosProducto->isNotEmpty()) {
            $ultimosCostos = DB::table('compra_detalles as cd')
                ->join('compras as c', 'c.id', '=', 'cd.compra_id')
                ->whereIn('cd.producto_codigo', $codigosProducto)
                ->orderByDesc('c.fecha_compra')
                ->orderByDesc('cd.id')
                ->get(['cd.producto_codigo', 'cd.precio_unitario'])
                ->unique('producto_codigo')
                ->pluck('precio_unitario', 'producto_codigo');
        }

        $totalGeneral = 0;

        $dataAsignaciones = $asignaciones->map(function ($a) use (&$totalGeneral, $ultimosCostos) {
            $costoUnitario = $a->costo_unitario ?? 0;

            if ((float) $costoUnitario <= 0 && isset($ultimosCostos[$a->producto_codigo])) {
                $costoUnitario = (float) $ultimosCostos[$a->producto_codigo];
            }

            $total = (float) $costoUnitario * (int) $a->cantidad_asignada;
            $fechaAsignacion = $a->fecha ? Carbon::parse($a->fecha) : null;
            $fechaVencimiento = $a->fecha_vencimiento ? Carbon::parse($a->fecha_vencimiento) : null;

            $estadoVidaUtil = 'Sin vida útil';

            if ($fechaVencimiento) {
                $estadoVidaUtil = now()->greaterThan($fechaVencimiento) ? 'Vencido' : 'Vigente';
            }

            $totalGeneral += $total;

            return [
                'producto' => $a->producto->nombre ?? '—',
                'producto_codigo' => $a->producto_codigo,
                'bodega' => $a->bodega->nombre ?? '—',
                'cantidad' => $a->cantidad_asignada,
                'costo_unitario' => (float) $costoUnitario,
                'total' => (float) $total,
                'fecha_asignacion' => $fechaAsignacion?->format('d/m/Y'),
                'fecha_vencimiento' => $fechaVencimiento?->format('d/m/Y'),
                'estado_vida_util' => $estadoVidaUtil,
            ];
        })->values();

        $cobros = collect();
        $totalCobros = 0;

        if (Schema::hasTable('alertas_reemplazos_rrhh')) {
            $cobros = AlertaReemplazo::query()
                ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
                ->where('alertas_reemplazos_rrhh.colaborador_codigo', $colaborador->codigo)
                ->addSelect('alertas_reemplazos_rrhh.*')
                ->addSelect('p.nombre as producto_nombre')
                ->addSelect('p.descripcion as producto_descripcion')
                ->selectRaw("
                    COALESCE(
                        (
                            SELECT cd.precio_unitario
                            FROM compra_detalles cd
                            WHERE cd.producto_codigo = alertas_reemplazos_rrhh.producto_codigo
                            ORDER BY cd.id DESC
                            LIMIT 1
                        ),
                        0
                    ) as costo_producto
                ")
                ->latest('alertas_reemplazos_rrhh.created_at')
                ->get()
                ->map(function ($alerta) use (&$totalCobros) {
                    $vidaUtilMeses = (int) ($alerta->vida_util_meses ?? 0);
                    $mesesRestantes = max(0, (int) ($alerta->meses_restantes ?? 0));
                    $costoProducto = (float) ($alerta->costo_producto ?? 0);

                    $montoCobro = ($alerta->descuento_aplicable && $vidaUtilMeses > 0)
                        ? round($costoProducto * ($mesesRestantes / $vidaUtilMeses), 2)
                        : 0;

                    $totalCobros += $montoCobro;

                    return [
                        'producto_codigo' => $alerta->producto_codigo,
                        'producto' => $alerta->producto_descripcion ?: ($alerta->producto_nombre ?: $alerta->producto_codigo),
                        'fecha_asignacion_anterior' => $alerta->fecha_asignacion_anterior
                            ? Carbon::parse($alerta->fecha_asignacion_anterior)->format('d/m/Y')
                            : '—',
                        'fecha_dano_reemplazo' => $alerta->fecha_dano_reemplazo
                            ? Carbon::parse($alerta->fecha_dano_reemplazo)->format('d/m/Y')
                            : '—',
                        'vida_util_meses' => $vidaUtilMeses,
                        'meses_restantes' => $mesesRestantes,
                        'costo_producto' => $costoProducto,
                        'descuento_aplicable' => (bool) $alerta->descuento_aplicable,
                        'monto_cobro' => $montoCobro,
                        'estado' => $alerta->estado ?? 'pendiente',
                        'detalle' => $alerta->detalle ?? '—',
                    ];
                })
                ->values();
        }

        return [
            'colaborador' => [
                'codigo' => $colaborador->codigo,
                'nombre' => $colaborador->nombre,
                'puesto' => $colaborador->puesto,
                'estado' => $colaborador->estado,
            ],
            'asignaciones' => $dataAsignaciones,
            'total_general' => $totalGeneral,
            'cobros' => $cobros,
            'total_cobros' => $totalCobros,
        ];
    }

    public function cambiarEstado(Colaborador $colaborador)
    {
        $nuevoEstado = $colaborador->estado === 'Activo' ? 'Inactivo' : 'Activo';

        $colaborador->update([
            'estado' => $nuevoEstado,
        ]);

        return redirect()
            ->route($this->colaboradoresRoutePrefix() . '.colaboradores.index')
            ->with('success', 'Estado del colaborador actualizado correctamente.');
    }

    private function colaboradoresRoutePrefix(): string
    {
        return auth()->user()->role_id == 4 ? 'rrhh' : 'admin';
    }
}