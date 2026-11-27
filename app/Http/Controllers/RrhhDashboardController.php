<?php

namespace App\Http\Controllers;

use App\Exports\AlertasRrhhExport;
use App\Models\AlertaReemplazo;
use App\Models\AsignacionInventario;
use App\Models\AsignacionInventarioArchivo;
use App\Models\MotivoAsignacion;
use App\Models\Region;
use App\Models\User;
use App\Models\VehiculoProductoArchivo;
use App\Services\DocumentoPrivado;
use App\Services\ResolverCustodia;
use App\Services\NotificacionService;
use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class RrhhDashboardController extends Controller
{
    public function index()
    {
        $totalAlertas = auth()->user() && app(Permisos::class)->permite(auth()->user(), 'alertas.consultar')
            ? $this->alertasVisibles(auth()->user())->whereIn('estado', [AlertaReemplazo::ESTADO_PENDIENTE, AlertaReemplazo::ESTADO_EN_REVISION])->count()
            : 0;

        return view('rrhh.dashboard', compact('totalAlertas'));
    }

    public function alertas(Request $request)
    {
        [$query] = $this->consulta($request);
        $alertas = $query->latest()->paginate(20)->withQueryString();

        $regiones = $this->regionesVisibles($request->user())->orderBy('nombre')->get();
        $motivos = $this->motivos();

        return view('rrhh.alertas.index', compact('alertas', 'regiones', 'motivos'));
    }

    public function show(AlertaReemplazo $alerta)
    {
        $this->autorizarAlertaActiva($alerta);
        $alerta->load([...AlertaReemplazo::RELACIONES_LISTADO, 'actuaciones.usuario', 'incidencia.archivoSubidoPor', 'incidencia.registrador',
            'asignacionAnterior.entrega', 'asignacionNueva.bodega']);
        $documentos = $this->documentosAsignacion($alerta);
        $refacciones = config('modulos.vehiculos') ? ($alerta->refaccion?->archivosFirmados()->get() ?? collect()) : collect();

        $historial = AsignacionInventario::where('colaborador_codigo', $alerta->colaborador_codigo)
            ->where('producto_codigo', $alerta->producto_codigo)->with('entrega', 'bodega', 'movimientos')->latest('id')->paginate(10, pageName: 'historial')->withQueryString();

        return view('rrhh.alertas.show', compact('alerta', 'documentos', 'refacciones', 'historial'));
    }

    public function evidencia(AlertaReemplazo $alerta)
    {
        $this->autorizarAlertaActiva($alerta);
        $incidencia = $alerta->incidencia;
        abort_unless($incidencia?->archivo, 404);

        return DocumentoPrivado::responder($incidencia->archivo, $incidencia->archivo_nombre);
    }

    public function documento(AlertaReemplazo $alerta, AsignacionInventarioArchivo $archivo)
    {
        $this->autorizarAlertaActiva($alerta);
        abort_unless($this->documentosAsignacion($alerta)->contains('id', $archivo->id), 404);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    public function documentoRefaccion(AlertaReemplazo $alerta, VehiculoProductoArchivo $archivo)
    {
        $this->autorizarAlertaActiva($alerta);
        abort_unless($alerta->refaccion?->archivosFirmados()->whereKey($archivo->id)->exists(), 404);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre_original);
    }

    private function documentosAsignacion(AlertaReemplazo $alerta): Collection
    {
        $previa = $alerta->incidencia?->asignacion ?? $alerta->asignacionAnterior;
        $nueva = $alerta->asignacionNueva;

        return collect([$previa, $nueva])->filter()->flatMap(fn (AsignacionInventario $asignacion) =>
            $asignacion->entrega?->documentos ?? $asignacion->evidencias)->unique('id');
    }

    public function actuacion(Request $request, AlertaReemplazo $alerta)
    {
        return $this->registrar($request, $alerta, AlertaReemplazo::ESTADO_EN_REVISION, 'nota');
    }

    public function regularizacion(Request $request, AlertaReemplazo $alerta)
    {
        return $this->registrar($request, $alerta, AlertaReemplazo::ESTADO_EN_REVISION, 'solicitar_regularizacion');
    }

    public function finalizar(Request $request, AlertaReemplazo $alerta)
    {
        $request->validate(['confirmar' => ['required', 'accepted']]);

        return $this->registrar($request, $alerta, AlertaReemplazo::ESTADO_FINALIZADO, 'finalizar');
    }

    private function registrar(Request $request, AlertaReemplazo $alerta, string $estado, string $accion)
    {
        $this->autorizarAlertaActiva($alerta);
        $nota = $request->validate(['nota' => ['required', 'string', 'max:4000']])['nota'];

        DB::transaction(function () use ($request, $alerta, $estado, $nota, $accion) {
            $custodia = $accion === 'solicitar_regularizacion'
                ? AsignacionInventario::whereKey($alerta->asignacion_anterior_id)->lockForUpdate()->first()
                : null;
            $bloqueada = $this->alertasVisibles($request->user())->whereKey($alerta->id)->lockForUpdate()->firstOrFail();
            if ($bloqueada->estado === AlertaReemplazo::ESTADO_FINALIZADO) {
                throw ValidationException::withMessages(['nota' => 'La incidencia ya fue finalizada.']);
            }
            if ($accion === 'solicitar_regularizacion' && ($custodia?->estado !== 'Activa' || !$custodia?->sustituida_por_id)) {
                throw ValidationException::withMessages(['nota' => 'La solicitud requiere una custodia anterior sin retorno confirmado.']);
            }
            $anterior = $bloqueada->estado;
            $bloqueada->update(['estado' => $estado]);
            $actuacion = $bloqueada->actuaciones()->create([
                'usuario_id' => $request->user()->id, 'estado' => $estado, 'nota' => $nota, 'accion' => $accion,
            ]);
            app(Auditoria::class)->registrar('alertas', $accion, $bloqueada->id, ['estado' => $anterior],
                ['estado' => $estado, 'actuacion_id' => $actuacion->id]);
        }, 3);

        if ($estado === AlertaReemplazo::ESTADO_FINALIZADO) {
            app(NotificacionService::class)->safeAction(fn ($service) => $service->notificarResolucionAlertaRrhh($alerta->refresh(), $request->user()));
        }
        if ($accion === 'solicitar_regularizacion') {
            app(NotificacionService::class)->safeAction(fn ($service) =>
                $service->notificarRegularizacionCustodia($alerta->asignacionAnterior, $request->user()));
        }
        $destino = app(Permisos::class)->ruta($request->user(), 'rrhh.alertas.show')
            ? route('rrhh.alertas.show', $alerta) : route('dashboard');

        return redirect()->to($destino)->with('success', 'Actuación registrada.');
    }

    public function export(Request $request)
    {
        abort_unless(app(Permisos::class)->permite($request->user(), 'alertas.consultar'), 403);
        [$query, $filtros] = $this->consulta($request);
        $alertas = $query->with(['colaborador.region', 'asignacionAnterior.entrega', 'incidencia.asignacion.entrega',
            'asignacionNueva.user', 'actuaciones' => fn ($q) => $q->with('usuario')->orderBy('id')])->latest()->get();

        return Excel::download(new AlertasRrhhExport($alertas, $request->user()->name, $filtros),
            'incidencias_rrhh_'.now()->format('Y-m-d_Hi').'.xlsx');
    }

    private function alertasVisibles(User $usuario): Builder
    {
        return AlertaReemplazo::query()
            ->when(!config('modulos.vehiculos'), fn (Builder $q) =>
                $q->whereNull('vehiculo_producto_asignacion_id')->whereNull('vehiculo_vin'))
            ->when($usuario->regionesOperativas()->exists(), fn (Builder $q) =>
                $q->whereHas('colaborador', fn (Builder $persona) =>
                    $persona->whereIn('region_id', $this->regionesVisibles($usuario)->select('regiones.id'))));
    }

    private function regionesVisibles(User $usuario): Builder
    {
        return Region::query()->when($usuario->regionesOperativas()->exists(), fn (Builder $q) =>
            $q->where('activa', true)->whereIn('id', $usuario->regionesOperativas()->select('regiones.id')));
    }

    private function motivos(): array
    {
        return MotivoAsignacion::orderBy('nombre')->pluck('nombre', 'id')
            ->mapWithKeys(fn ($nombre, $id) => ['motivo-'.$id => $nombre])
            ->all() + collect(ResolverCustodia::CONDICIONES)->mapWithKeys(fn ($nombre, $clave) => ['condicion-'.$clave => $nombre])->all();
    }

    private function autorizarAlertaActiva(AlertaReemplazo $alerta): void
    {
        abort_unless($this->alertasVisibles(auth()->user())->whereKey($alerta->id)->exists(), 403);
    }

    private function consulta(Request $request): array
    {
        $motivos = $this->motivos();
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(array_keys(AlertaReemplazo::ESTADOS))],
            'region' => ['nullable', 'integer'],
            'motivo' => ['nullable', Rule::in(array_keys($motivos))],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($request->filled('desde') ? ['after_or_equal:desde'] : [])],
        ]);
        $consulta = $this->alertasVisibles($request->user())->with(AlertaReemplazo::RELACIONES_LISTADO);
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
        if (!empty($datos['region'])) {
            $region = $this->regionesVisibles($request->user())->findOrFail($datos['region']);
            $consulta->whereHas('colaborador', fn (Builder $q) => $q->where('region_id', $region->id));
            $filtros['Región'] = $region->nombre;
        }
        if (!empty($datos['motivo'])) {
            [$tipo, $valor] = explode('-', $datos['motivo'], 2);
            if ($tipo === 'motivo') {
                $consulta->whereHas('asignacionNueva', fn (Builder $q) => $q->where('motivo_asignacion_id', $valor));
            } else {
                $consulta->whereHas('incidencia', fn (Builder $q) => $q->where('motivo', $valor));
            }
            $filtros['Motivo'] = $motivos[$datos['motivo']];
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $comparacion) {
            if (!empty($datos[$campo])) {
                $fecha = $datos[$campo];
                $consulta->where(fn (Builder $q) => $q->whereHas('incidencia',
                    fn (Builder $incidencia) => $incidencia->whereDate('ocurrio_en', $comparacion, $fecha))
                    ->orWhere(fn (Builder $anterior) => $anterior->whereNull('incidencia_inventario_id')
                        ->whereDate('fecha_dano_reemplazo', $comparacion, $fecha)));
                $filtros[ucfirst($campo)] = $datos[$campo];
            }
        }

        return [$consulta, $filtros];
    }
}
