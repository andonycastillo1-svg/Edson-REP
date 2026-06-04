<?php

namespace App\Http\Controllers;

use App\Models\AlertaReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RrhhDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalAlertas = $this->buildQuery($request)
            ->where('alertas_reemplazos_rrhh.estado', AlertaReemplazo::ESTADO_PENDIENTE)
            ->count();

        return view('rrhh.dashboard', compact('totalAlertas'));
    }

    public function alertas(Request $request)
    {
        $alertas = $this->buildQuery($request)
            ->latest('alertas_reemplazos_rrhh.created_at')
            ->paginate(20)
            ->appends($request->query());

        $alertas->through(fn ($a) => $this->mapAlerta($a));

        return view('rrhh.alertas.index', compact('alertas'));
    }

    public function finalizar(Request $request, AlertaReemplazo $alerta)
    {
        $request->validate([
            'confirmar' => ['required', 'accepted'],
        ]);

        if (!$alerta->estaPendiente()) {
            return back()->with('info', 'La alerta ya se encuentra finalizada.');
        }

        $alerta->marcarFinalizada();

        return back()->with('success', 'Alerta de descuento marcada como finalizada.');
    }

    public function export(Request $request)
    {
        $alertas = $this->buildQuery($request)
            ->latest('alertas_reemplazos_rrhh.created_at')
            ->get()
            ->map(fn ($a) => $this->mapAlerta($a));

        return response()->streamDownload(function () use ($alertas) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Colaborador ID',
                'Colaborador',
                'Código producto',
                'Producto',
                'Fecha asignación',
                'Fecha daño/reemplazo',
                'Vida útil meses',
                'Vida restante',
                'Descuento aplica',
                'Monto descuento',
                'Estado',
            ]);

            foreach ($alertas as $a) {
                fputcsv($out, [
                    $a->colaborador_codigo,
                    $a->colaborador_nombre ?? 'Sin nombre',
                    $a->producto_codigo,
                    $a->producto_descripcion ?: ($a->producto_nombre ?: $a->producto_codigo),
                    optional($a->fecha_asignacion_anterior)->format('d/m/Y'),
                    optional($a->fecha_dano_reemplazo)->format('d/m/Y'),
                    $a->vida_util_meses,
                    $a->meses_restantes_reales,
                    $a->descuento_aplicable ? 'Sí' : 'No',
                    number_format($a->descuento_calculado, 2, '.', ''),
                    $a->estado_etiqueta,
                ]);
            }

            fclose($out);
        }, 'reporte_descuentos_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildQuery(Request $request)
    {
        if (!Schema::hasTable('alertas_reemplazos_rrhh')) {
            return AlertaReemplazo::query()->whereRaw('1 = 0');
        }

        return AlertaReemplazo::query()
            ->leftJoin('colaboradores as c', 'c.codigo', '=', 'alertas_reemplazos_rrhh.colaborador_codigo')
            ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
            ->addSelect('alertas_reemplazos_rrhh.*')
            ->addSelect('c.nombre as colaborador_nombre')
            ->addSelect('p.nombre as producto_nombre')
            ->addSelect('p.descripcion as producto_descripcion')
            ->selectRaw("
                GREATEST(
                    alertas_reemplazos_rrhh.vida_util_meses - TIMESTAMPDIFF(
                        MONTH,
                        alertas_reemplazos_rrhh.fecha_asignacion_anterior,
                        alertas_reemplazos_rrhh.fecha_dano_reemplazo
                    ),
                    0
                ) as meses_restantes_reales
            ")
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
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim($request->query('q'));

                $query->where(function ($w) use ($q) {
                    $w->where('alertas_reemplazos_rrhh.colaborador_codigo', 'like', "%{$q}%")
                        ->orWhere('alertas_reemplazos_rrhh.producto_codigo', 'like', "%{$q}%")
                        ->orWhere('c.nombre', 'like', "%{$q}%")
                        ->orWhere('p.nombre', 'like', "%{$q}%")
                        ->orWhere('p.descripcion', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $estado = $request->query('estado');

                if (in_array($estado, [AlertaReemplazo::ESTADO_PENDIENTE, AlertaReemplazo::ESTADO_FINALIZADO], true)) {
                    $query->where('alertas_reemplazos_rrhh.estado', $estado);
                }
            })
            ->when($request->filled('desde'), function ($query) use ($request) {
                $query->whereDate(
                    'alertas_reemplazos_rrhh.fecha_dano_reemplazo',
                    '>=',
                    $request->query('desde')
                );
            })
            ->when($request->filled('hasta'), function ($query) use ($request) {
                $query->whereDate(
                    'alertas_reemplazos_rrhh.fecha_dano_reemplazo',
                    '<=',
                    $request->query('hasta')
                );
            });
    }

    private function mapAlerta($alerta)
    {
        $vidaUtilMeses = (int) ($alerta->vida_util_meses ?? 0);
        $mesesRestantes = max(0, (int) ($alerta->meses_restantes_reales ?? 0));
        $costoProducto = (float) ($alerta->costo_producto ?? 0);

        $alerta->meses_restantes_reales = $mesesRestantes;

        $alerta->descuento_calculado = ($alerta->descuento_aplicable && $vidaUtilMeses > 0)
            ? round($costoProducto * ($mesesRestantes / $vidaUtilMeses), 2)
            : 0;

        $estado = $alerta->estado ?? AlertaReemplazo::ESTADO_PENDIENTE;
        $alerta->estado_etiqueta = $estado === AlertaReemplazo::ESTADO_FINALIZADO
            ? 'Finalizado'
            : 'Pendiente';

        return $alerta;
    }
}
