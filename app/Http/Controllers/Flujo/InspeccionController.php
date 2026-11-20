<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Inspeccion;
use App\Models\Region;
use App\Models\User;
use App\Services\DocumentoPrivado;
use App\Services\FlujoAcceso;
use App\Sistema\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InspeccionController extends Controller
{
    public function __construct(private FlujoAcceso $acceso) {}

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'], 'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'coordinador' => ['nullable', 'integer'], 'supervisor' => ['nullable', 'integer'], 'region' => ['nullable', 'integer'],
        ]);
        $usuario = $request->user();
        $consulta = $this->acceso->inspecciones($usuario)->with('region', 'supervisor.coordinador', 'colaborador', 'consolidados');
        foreach (['supervisor' => 'supervisor_id', 'region' => 'region_id'] as $filtro => $campo) {
            if (!empty($filtros[$filtro])) {
                $consulta->where($campo, $filtros[$filtro]);
            }
        }
        if (!empty($filtros['coordinador'])) {
            $consulta->whereHas('supervisor', fn ($q) => $q->where('coordinador_id', $filtros['coordinador']));
        }
        if (!empty($filtros['desde'])) {
            $consulta->where('fecha', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $consulta->where('fecha', '<=', $filtros['hasta']);
        }
        $supervisores = $this->acceso->inspecciones($usuario)->distinct()->pluck('supervisor_id');
        $coordinadores = User::whereKey($supervisores->all())->whereNotNull('coordinador_id')->distinct()->pluck('coordinador_id');
        $personas = User::whereKey($supervisores->merge($coordinadores)->unique()->all())->get()->keyBy('id');
        $regiones = Region::orderBy('nombre')->get();
        $inspecciones = $consulta->latest('id')->paginate($this->acceso->puede($usuario, 'consolidar') ? 100 : 30)->withQueryString();

        return view('flujo.inspecciones', compact('inspecciones', 'regiones', 'personas', 'supervisores', 'coordinadores'));
    }

    public function create(Request $request)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        $regiones = Region::where('activa', true)->orderBy('nombre')->get();
        $secciones = DB::table('inspeccion_secciones')->orderBy('orden')->get();
        $articulos = DB::table('inspeccion_articulos')->where('activo', true)->whereNotNull('producto_codigo')
            ->orderBy('orden')->orderBy('id')->get()->groupBy('seccion');
        $tieneCoordinador = User::deAmbito(5)->whereKey((int) $request->user()->coordinador_id)->exists();

        return view('flujo.inspeccion-form', compact('regiones', 'secciones', 'articulos', 'tieneCoordinador'));
    }

    public function colaboradores(Request $request, Region $region)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        abort_unless($region->activa, 404);

        return response()->json(Colaborador::where('region_id', $region->id)->where('estado', 'Activo')
            ->orderBy('nombre')->get(['codigo', 'nombre', 'puesto']))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->acceso->autorizar($request->user(), 'capturar');
        $datos = $request->validate([
            'solicitud_id' => ['required', 'uuid'], 'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'region_id' => ['required', 'integer', 'exists:regiones,id'], 'colaborador_codigo' => ['required', 'string', 'max:255'],
            'cantidades' => ['nullable', 'array', 'max:1000'], 'cantidades.*' => ['nullable', 'integer', 'between:0,10000'],
            'fotos' => ['nullable', 'array', 'max:8'], 'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $archivos = [];
        try {
            foreach ($request->file('fotos', []) as $foto) {
                $archivos[] = ['ruta' => DocumentoPrivado::guardar($foto, 'inspecciones'), 'nombre' => Str::limit($foto->getClientOriginalName(), 240, '')];
            }
            $inspeccion = DB::transaction(function () use ($datos, $request, $archivos) {
                $usuario = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if (!User::deAmbito(5)->whereKey((int) $usuario->coordinador_id)->exists()) {
                    throw ValidationException::withMessages(['colaborador_codigo' => 'Tu cuenta necesita un coordinador activo.']);
                }
                $colaborador = Colaborador::whereKey($datos['colaborador_codigo'])->lockForUpdate()->first();
                if (!$colaborador || $colaborador->estado !== 'Activo' || (int) $colaborador->region_id !== (int) $datos['region_id']
                    || blank($colaborador->puesto) || !Region::whereKey($datos['region_id'])->where('activa', true)->exists()) {
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
                    if (!$articulo || $cantidad > $articulo->maximo) {
                        throw ValidationException::withMessages(['cantidades.'.$id => 'La cantidad supera el máximo o el artículo ya no está disponible.']);
                    }
                    $lineas[] = ['producto_codigo' => $articulo->producto_codigo, 'etiqueta' => $articulo->etiqueta, 'cantidad' => $cantidad];
                }
                $fila = Inspeccion::create([
                    'supervisor_id' => $usuario->id, 'coordinador_id' => $usuario->coordinador_id, 'region_id' => $datos['region_id'],
                    'colaborador_codigo' => $colaborador->codigo, 'puesto' => $colaborador->puesto, 'fecha' => $datos['fecha'],
                    'solicitud_id' => $datos['solicitud_id'],
                ]);
                $fila->lineas()->createMany($lineas);
                $fila->fotos()->createMany($archivos);
                app(Auditoria::class)->registrar('flujo', 'registrar_inspeccion', $fila->id, null, ['articulos' => count($lineas)]);

                return $fila;
            }, 3);
        } catch (\Throwable $e) {
            foreach ($archivos as $archivo) {
                DocumentoPrivado::descartar($archivo['ruta']);
            }
            throw $e;
        }

        return redirect()->route('flujo.inspecciones.show', $inspeccion)->with('success', 'Inspección registrada.');
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
