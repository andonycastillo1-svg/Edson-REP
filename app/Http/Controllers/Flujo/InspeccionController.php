<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Models\Colaborador;
use App\Models\Inspeccion;
use App\Models\Region;
use App\Models\ProcesoInspeccion;
use App\Services\RegionesCoordinacion;
use App\Models\User;
use App\Services\DocumentoPrivado;
use App\Services\FlujoAcceso;
use App\Sistema\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InspeccionController extends Controller
{
    public function __construct(private FlujoAcceso $acceso) {}

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'proceso' => ['nullable', Rule::anyOf(['integer', 'in:historico'])],
            'coordinador' => ['nullable', 'integer'], 'supervisor' => ['nullable', 'integer'], 'region' => ['nullable', 'integer'],
        ]);
        $usuario = $request->user();
        $exportar = $request->boolean('xlsx');
        $consulta = $this->acceso->inspecciones($usuario, $exportar ? 'exportar_inspecciones' : 'inspecciones')->with('region.coordinador', 'supervisor', 'colaborador', 'consolidados');
        foreach (['supervisor' => 'supervisor_id', 'region' => 'region_id'] as $filtro => $campo) {
            if (!empty($filtros[$filtro])) {
                $consulta->where($campo, $filtros[$filtro]);
            }
        }
        if (($filtros['proceso'] ?? null) === 'historico') {
            $consulta->whereNull('proceso_id');
        } elseif (!empty($filtros['proceso'])) {
            $consulta->where('proceso_id', $filtros['proceso']);
        }
        if (!empty($filtros['coordinador'])) {
            $consulta->where(function ($q) use ($filtros) {
                $q->where('coordinador_id', $filtros['coordinador'])
                    ->orWhereHas('region', fn ($r) => $r->where('coordinador_id', $filtros['coordinador'])
                        ->orWhereHas('coordinadoresAdicionales', fn ($u) => $u->whereKey($filtros['coordinador'])));
            });
        }
        if (!empty($filtros['desde'])) {
            $consulta->where('fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $consulta->where('fecha', '<=', $filtros['hasta']);
        }
        if ($exportar) {
            return app(ExportacionesOperativas::class)->inspecciones($consulta);
        }
        $supervisores = $this->acceso->inspecciones($usuario)->distinct()->pluck('supervisor_id');
        $regiones = Region::whereIn('id', $this->acceso->inspecciones($usuario)->select('region_id'))->orderBy('nombre')->get();
        $opciones = app(RegionesCoordinacion::class)->opciones($regiones->modelKeys());
        $coordinadores = $opciones->flatten(1)->pluck('id')->unique()->values();
        $procesos = ProcesoInspeccion::orderBy('nombre')->get();
        $personas = User::whereKey($supervisores->merge($coordinadores)->unique()->all())->get()->keyBy('id');
        $inspecciones = $consulta->latest('id')->paginate($this->acceso->puede($usuario, 'consolidar') ? 100 : 30)->withQueryString();

        return view('flujo.inspecciones', compact('inspecciones', 'regiones', 'personas', 'supervisores', 'coordinadores', 'procesos', 'opciones'));
    }

    public function create(Request $request)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        $regiones = Region::whereIn('id', $this->acceso->regiones($request->user(), 'capturar'))->orderBy('nombre')->get();
        $secciones = DB::table('inspeccion_secciones')->orderBy('orden')->get();
        $articulos = DB::table('inspeccion_articulos')->where('activo', true)->whereNotNull('producto_codigo')
            ->orderBy('orden')->orderBy('id')->get()->groupBy('seccion');
        $procesos = ProcesoInspeccion::where('activo', true)->orderBy('nombre')->get();
        $preferencias = $request->validate([
            'proceso' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'region' => ['nullable', 'integer'],
        ]);

        return view('flujo.inspeccion-form', compact('regiones', 'secciones', 'articulos', 'preferencias', 'procesos'));
    }

    public function colaboradores(Request $request, Region $region)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        abort_unless($this->acceso->regiones($request->user(), 'capturar')->whereKey($region->id)->exists(), 404);

        return response()->json(Colaborador::where('region_id', $region->id)->where('estado', 'Activo')
            ->orderBy('nombre')->get(['codigo', 'nombre', 'puesto']))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        $datos = $request->validate([
            'solicitud_id' => ['required', 'uuid'], 'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'proceso_id' => ['required', 'integer', 'exists:procesos_inspeccion,id'],
            'region_id' => ['required', 'integer', 'exists:regiones,id'], 'colaborador_codigo' => ['required', 'string', 'max:255'],
            'cantidades' => ['nullable', 'array', 'max:1000'], 'cantidades.*' => ['nullable', 'integer', 'min:0', Rule::anyOf(['integer:strict', 'string'])],
            'observaciones' => ['nullable', 'string', 'max:4000'], 'destino' => ['nullable', 'in:continuar,finalizar'],
            'fotos' => ['nullable', 'array', 'max:8'], 'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $archivos = [];
        try {
            foreach ($request->file('fotos', []) as $foto) {
                $archivos[] = ['ruta' => DocumentoPrivado::guardar($foto, 'inspecciones'), 'nombre' => Str::limit($foto->getClientOriginalName(), 240, '')];
            }
            $inspeccion = DB::transaction(function () use ($datos, $request, $archivos) {
                $usuario = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $this->acceso->autorizar($usuario, 'capturar');
                $region = $this->acceso->regiones($usuario, 'capturar')->whereKey($datos['region_id'])->first();
                $proceso = ProcesoInspeccion::whereKey($datos['proceso_id'])->where('activo', true)->lockForUpdate()->first();
                if (!$proceso) {
                    throw ValidationException::withMessages(['proceso_id' => 'Selecciona un proceso activo.']);
                }
                $colaborador = Colaborador::whereKey($datos['colaborador_codigo'])->lockForUpdate()->first();
                if (!$colaborador || $colaborador->estado !== 'Activo' || (int) $colaborador->region_id !== (int) $datos['region_id']
                    || blank($colaborador->puesto) || !$region) {
                    throw ValidationException::withMessages(['colaborador_codigo' => 'Selecciona un colaborador activo de esta región con puesto registrado.']);
                }
                if (Inspeccion::where('supervisor_id', $usuario->id)->where('solicitud_id', $datos['solicitud_id'])->exists()) {
                    throw ValidationException::withMessages(['solicitud_id' => 'La inspección ya se registró.']);
                }
                $cantidades = collect($datos['cantidades'] ?? [])->filter(fn ($cantidad) => (int) $cantidad > 0);
                $catalogo = DB::table('inspeccion_articulos')->whereIn('id', $cantidades->keys())->where('activo', true)
                    ->whereNotNull('producto_codigo')->lockForUpdate()->get()->keyBy('id');
                $lineas = [];
                foreach ($cantidades as $id => $cantidad) {
                    $articulo = $catalogo->get($id);
                    if (!$articulo) {
                        throw ValidationException::withMessages(['cantidades.'.$id => 'El artículo ya no está disponible.']);
                    }
                    $lineas[] = ['producto_codigo' => $articulo->producto_codigo, 'etiqueta' => $articulo->etiqueta, 'cantidad' => $cantidad];
                }
                $opciones = app(RegionesCoordinacion::class)->opciones([(int) $region->id])->get($region->id);
                $fila = Inspeccion::create([
                    'supervisor_id' => $usuario->id, 'coordinador_id' => $opciones->count() === 1 ? $opciones->sole()->id : null,
                    'proceso_id' => $proceso->id, 'proceso_nombre' => $proceso->nombre,
                    'region_id' => $region->id, 'captura_regional' => true, 'observaciones' => $datos['observaciones'] ?? null,
                    'colaborador_codigo' => $colaborador->codigo, 'puesto' => $colaborador->puesto, 'fecha' => $datos['fecha'],
                    'solicitud_id' => $datos['solicitud_id'],
                ]);
                $fila->lineas()->createMany($lineas);
                $fila->fotos()->createMany($archivos);
                $this->acceso->registrarCaptura($usuario, $region->id, $fila->id);
                app(Auditoria::class)->registrar('flujo', 'registrar_inspeccion', $fila->id, null, ['articulos' => count($lineas), 'proceso_id' => $proceso->id]);

                return $fila;
            }, 3);
        } catch (\Throwable $e) {
            foreach ($archivos as $archivo) {
                DocumentoPrivado::descartar($archivo['ruta']);
            }
            throw $e;
        }

        $respuesta = match ($datos['destino'] ?? null) {
            'continuar' => redirect()->route('flujo.inspecciones.create', ['fecha' => $datos['fecha'], 'region' => $datos['region_id'], 'proceso' => $datos['proceso_id'], 'continuar' => 1]),
            'finalizar' => redirect()->route('flujo.inspecciones.index'),
            default => redirect()->route('flujo.inspecciones.show', $inspeccion),
        };

        return $respuesta->with('success', 'Inspección registrada.');
    }

    public function show(Request $request, int $inspeccion)
    {
        $inspeccion = $this->acceso->inspecciones($request->user())->with('supervisor', 'region', 'colaborador', 'lineas', 'fotos', 'consolidados')->findOrFail($inspeccion);

        return view('flujo.inspeccion', compact('inspeccion'));
    }

    public function foto(Request $request, int $inspeccion, int $foto)
    {
        $archivo = $this->acceso->inspecciones($request->user())->findOrFail($inspeccion)->fotos()->findOrFail($foto);

        return DocumentoPrivado::responder($archivo->ruta, $archivo->nombre);
    }
}
