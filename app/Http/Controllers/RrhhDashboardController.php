<?php

namespace App\Http\Controllers;

use App\Exports\AlertasRrhhExport;
use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventarioArchivo;
use App\Models\VehiculoProductoArchivo;
use Illuminate\Support\Collection;
use App\Services\DocumentoPrivado;
use App\Services\NotificacionService;
use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class RrhhDashboardController extends Controller
{
    public function index()
    {
        $totalAlertas = auth()->user() && app(Permisos::class)->permite(auth()->user(), 'alertas.consultar')
            ? AlertaReemplazo::whereIn('estado', [AlertaReemplazo::ESTADO_PENDIENTE, AlertaReemplazo::ESTADO_EN_REVISION])->count()
            : 0;

        return view('rrhh.dashboard', compact('totalAlertas'));
    }

    public function alertas(Request $request)
    {
        [$query] = $this->consulta($request);
        $alertas = $query->latest()->paginate(20)->withQueryString();

        return view('rrhh.alertas.index', compact('alertas'));
    }

    public function show(AlertaReemplazo $alerta)
    {
        $alerta->load([...AlertaReemplazo::RELACIONES_LISTADO, 'actuaciones.usuario', 'incidencia.archivoSubidoPor']);
        $documentos = $this->documentosAsignacion($alerta);
        $refacciones = $alerta->refaccion?->archivosFirmados()->get() ?? collect();

        return view('rrhh.alertas.show', compact('alerta', 'documentos', 'refacciones'));
    }

    public function evidencia(AlertaReemplazo $alerta)
    {
        $incidencia = $alerta->incidencia;
        abort_unless($incidencia?->archivo, 404);

        return DocumentoPrivado::responder($incidencia->archivo, $incidencia->archivo_nombre);
    }

    public function documento(AlertaReemplazo $alerta, AsignacionInventarioArchivo $archivo)
    {
        abort_unless($this->documentosAsignacion($alerta)->contains($archivo), 404);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    public function documentoRefaccion(AlertaReemplazo $alerta, VehiculoProductoArchivo $archivo)
    {
        abort_unless($alerta->refaccion?->archivosFirmados()->whereKey($archivo->id)->exists(), 404);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    private function documentosAsignacion(AlertaReemplazo $alerta): Collection
    {
        $asignacion = $alerta->incidencia?->asignacion ?? $alerta->asignacionAnterior;

        return $asignacion?->entrega?->documentos ?? $asignacion?->evidencias ?? collect();
    }

    public function actuacion(Request $request, AlertaReemplazo $alerta)
    {
        return $this->registrar($request, $alerta, AlertaReemplazo::ESTADO_EN_REVISION);
    }

    public function finalizar(Request $request, AlertaReemplazo $alerta)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        return $this->registrar($request, $alerta, AlertaReemplazo::ESTADO_FINALIZADO);
    }

    private function registrar(Request $request, AlertaReemplazo $alerta, string $estado)
    {
        $nota = $request->validate(['nota' => ['required', 'string', 'max:4000']])['nota'];

        DB::transaction(function () use ($request, $alerta, $estado, $nota) {
            $bloqueada = AlertaReemplazo::whereKey($alerta->id)->lockForUpdate()->firstOrFail();
            if ($bloqueada->estado === AlertaReemplazo::ESTADO_FINALIZADO) {
                throw ValidationException::withMessages(['nota' => 'La incidencia ya fue finalizada.']);
            }
            $anterior = $bloqueada->estado;
            $bloqueada->update(['estado' => $estado]);
            $actuacion = $bloqueada->actuaciones()->create([
                'usuario_id' => $request->user()->id, 'estado' => $estado, 'nota' => $nota,
            ]);
            app(Auditoria::class)->registrar('alertas', 'actuacion', $bloqueada->id, ['estado' => $anterior],
                ['estado' => $estado, 'actuacion_id' => $actuacion->id]);
        }, 3);

        if ($estado === AlertaReemplazo::ESTADO_FINALIZADO) {
            app(NotificacionService::class)->safeAction(fn ($service) => $service->notificarResolucionAlertaRrhh($alerta->refresh(), $request->user()));
        }
        $destino = app(Permisos::class)->ruta($request->user(), 'rrhh.alertas.show')
            ? route('rrhh.alertas.show', $alerta) : route('dashboard');

        return redirect()->to($destino)->with('success', 'Actuación registrada.');
    }

    public function export(Request $request)
    {
        [$query, $filtros] = $this->consulta($request);

        return Excel::download(new AlertasRrhhExport($query->latest()->get(), $request->user()->name, $filtros),
            'incidencias_rrhh_'.now()->format('Y-m-d_Hi').'.xlsx');
    }

    private function consulta(Request $request): array
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_keys(AlertaReemplazo::ESTADOS))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $consulta = AlertaReemplazo::with(AlertaReemplazo::RELACIONES_LISTADO);
        $filtros = [];
        if (!empty($datos['q'])) {
            $filtros['Búsqueda'] = $datos['q'];
            $patron = '%'.$datos['q'].'%';
            $consulta->where(function (Builder $q) use ($patron) {
                $q->where('colaborador_codigo', 'like', $patron)->orWhere('producto_codigo', 'like', $patron)
                    ->orWhereHas('colaborador', fn (Builder $c) => $c->where('nombre', 'like', $patron))
                    ->orWhereHas('producto', fn (Builder $p) => $p->where('nombre', 'like', $patron)->orWhere('descripcion', 'like', $patron));
            });
        }
        if (!empty($datos['estado'])) {
            $consulta->where('estado', $datos['estado']);
            $filtros['Estado'] = AlertaReemplazo::ESTADOS[$datos['estado']];
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $comparacion) {
            if (!empty($datos[$campo])) {
                $consulta->whereDate('fecha_dano_reemplazo', $comparacion, $datos[$campo]);
                $filtros[ucfirst($campo)] = $datos[$campo];
            }
        }

        return [$consulta, $filtros];
    }
}
