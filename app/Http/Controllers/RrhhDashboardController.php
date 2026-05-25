<?php

namespace App\Http\Controllers;

use App\Models\AlertaReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RrhhDashboardController extends Controller
{
    public function index(Request $request)
    {
        $alertas = $this->buildQuery($request)->latest()->paginate(20)->appends($request->query());
        $alertas->through(fn ($a) => $this->mapAlerta($a));
        return view('rrhh.dashboard', compact('alertas'));
    }

    public function export(Request $request)
    {
        $alertas = $this->buildQuery($request)->latest()->get()->map(fn ($a) => $this->mapAlerta($a));
        return response()->streamDownload(function () use ($alertas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Colaborador ID', 'Colaborador', 'Codigo producto', 'Producto', 'Fecha asignacion', 'Fecha dano/reemplazo', 'Vida util meses', 'Vida restante', 'Descuento aplica', 'Monto descuento']);
            foreach ($alertas as $a) {
                fputcsv($out, [$a->colaborador_codigo, $a->colaborador_nombre, $a->producto_codigo, $a->producto_descripcion ?: $a->producto_nombre, $a->fecha_asignacion_anterior, $a->fecha_dano_reemplazo, $a->vida_util_meses, $a->meses_restantes_reales, $a->descuento_aplicable ? 'Si' : 'No', $a->descuento_calculado]);
            }
            fclose($out);
        }, 'reporte_descuentos_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildQuery(Request $request)
    {
        if (!Schema::hasTable('alertas_reemplazos_rrhh')) {
            return AlertaReemplazo::query()->whereRaw('1=0');
        }
        return AlertaReemplazo::query()
            ->leftJoin('colaboradores as c', 'c.codigo', '=', 'alertas_reemplazos_rrhh.colaborador_codigo')
            ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
            ->addSelect('alertas_reemplazos_rrhh.*', 'c.nombre as colaborador_nombre', 'p.nombre as producto_nombre', 'p.descripcion as producto_descripcion')
            ->selectRaw("GREATEST(alertas_reemplazos_rrhh.vida_util_meses - TIMESTAMPDIFF(MONTH, alertas_reemplazos_rrhh.fecha_asignacion_anterior, alertas_reemplazos_rrhh.fecha_dano_reemplazo), 0) as meses_restantes_reales")
            ->selectRaw("COALESCE((SELECT cd.precio_unitario FROM compra_detalles cd WHERE cd.producto_codigo = alertas_reemplazos_rrhh.producto_codigo ORDER BY cd.id DESC LIMIT 1), 0) as costo_producto")
            ->when($request->query('q'), function ($query, $q) {
                $query->where(function ($w) use ($q) {
                    $w->where('alertas_reemplazos_rrhh.colaborador_codigo', 'like', "%{$q}%")
                        ->orWhere('alertas_reemplazos_rrhh.producto_codigo', 'like', "%{$q}%")
                        ->orWhere('c.nombre', 'like', "%{$q}%")
                        ->orWhere('p.nombre', 'like', "%{$q}%");
                });
            })
            ->when($request->query('desde'), fn ($query, $desde) => $query->whereDate('alertas_reemplazos_rrhh.fecha_dano_reemplazo', '>=', $desde))
            ->when($request->query('hasta'), fn ($query, $hasta) => $query->whereDate('alertas_reemplazos_rrhh.fecha_dano_reemplazo', '<=', $hasta));
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
        return $alerta;
    }
}
