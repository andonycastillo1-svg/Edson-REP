<?php

namespace App\Http\Controllers;

use App\Exports\AlertasRrhhExport;
use App\Models\AlertaReemplazo;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Bodega;
use Maatwebsite\Excel\Facades\Excel;

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
        $this->autorizarVisualizacionAlertas($request);
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

        if (! $alerta->estaPendiente()) {
            return back()->with('info', 'La alerta ya se encuentra finalizada.');
        }

        $alerta->marcarFinalizada();
        app(NotificacionService::class)->safeAction(
            fn (NotificacionService $service) => $service->notificarResolucionAlertaRrhh($alerta, $request->user())
        );

        return back()->with('success', 'Alerta de descuento marcada como finalizada.');
    }

    public function export(Request $request)
    {
        $this->autorizarVisualizacionAlertas($request);
        $alertas = $this->buildQuery($request)
            ->latest('alertas_reemplazos_rrhh.created_at')
            ->get()
            ->map(fn ($alerta) => $this->mapAlerta($alerta));

        $filters = [];
        if ($request->filled('q')) {
            $filters['Búsqueda'] = trim((string) $request->query('q'));
        }
        if ($request->filled('estado')) {
            $filters['Estado'] = ucfirst((string) $request->query('estado'));
        }
        if ($request->filled('desde')) {
            $filters['Desde'] = $request->query('desde');
        }
        if ($request->filled('hasta')) {
            $filters['Hasta'] = $request->query('hasta');
        }

        return Excel::download(
            new AlertasRrhhExport($alertas, $request->user()->name, $filters),
            'alertas_rrhh_'.now()->format('Y-m-d_Hi').'.xlsx'
        );
    }

    private function autorizarVisualizacionAlertas(Request $request): void
    {
        $user = $request->user();

        if ((int) $user->role_id === 4) {
            return;
        }

        if ((int) $user->role_id === 2) {
            $bodega = $user->bodega_id ? Bodega::find($user->bodega_id) : null;

            if ($bodega && $bodega->tipo === 'Principal') {
                return;
            }
        }

        abort(403);
    }

    private function buildQuery(Request $request)
    {
        if (! Schema::hasTable('alertas_reemplazos_rrhh')) {
            return AlertaReemplazo::query()->whereRaw('1 = 0');
        }

        return AlertaReemplazo::query()
            ->leftJoin('colaboradores as c', 'c.codigo', '=', 'alertas_reemplazos_rrhh.colaborador_codigo')
            ->leftJoin('productos as p', 'p.codigo', '=', 'alertas_reemplazos_rrhh.producto_codigo')
            ->leftJoin('users as u', 'u.id', '=', 'alertas_reemplazos_rrhh.registrado_por_user_id')
            ->addSelect('alertas_reemplazos_rrhh.*')
            ->addSelect('c.nombre as colaborador_nombre')
            ->addSelect('p.nombre as producto_nombre')
            ->addSelect('p.descripcion as producto_descripcion')
            ->addSelect('u.name as registrado_por_nombre')
            ->selectRaw('
                GREATEST(
                    alertas_reemplazos_rrhh.vida_util_meses - TIMESTAMPDIFF(
                        MONTH,
                        alertas_reemplazos_rrhh.fecha_asignacion_anterior,
                        alertas_reemplazos_rrhh.fecha_dano_reemplazo
                    ),
                    0
                ) as meses_restantes_reales
            ')
            ->selectRaw('
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
            ')
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
