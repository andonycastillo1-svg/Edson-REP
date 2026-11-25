<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Services\ExportacionesOperativas;
use App\Models\Colaborador;
use App\Models\Consolidado;
use App\Models\Producto;
use App\Models\User;
use App\Services\ConsolidacionInspecciones;
use App\Services\DetalleConsolidado;
use App\Services\FlujoAcceso;
use App\Services\PlanCompras;
use App\Services\RegionesCoordinacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsolidadoController extends Controller
{
    private const TRANSICIONES = [
        'enviar' => ['enviar_consolidados', ['borrador', 'correccion'], 'enviado'],
        'revisar' => ['revisar', ['enviado'], 'revision'],
        'preparar_detalle' => ['revisar', ['enviado', 'revision'], 'revision'],
        'ajustar' => ['ajustar', ['enviado', 'revision'], 'revision'],
        'aprobar' => ['aprobar', ['enviado', 'revision'], 'aprobado'],
        'devolver' => ['aprobar', ['enviado', 'revision'], 'correccion'],
        'retirar' => ['enviar_consolidados', ['enviado', 'revision'], 'correccion'],
        'reabrir' => ['reabrir', ['aprobado'], 'correccion'],
        'reasignar' => ['configurar', ['enviado', 'revision', 'correccion'], 'enviado'],
        'cerrar' => ['cerrar_compras', ['aprobado'], 'cerrado'],
    ];

    public function __construct(
        private FlujoAcceso $acceso,
        private ConsolidacionInspecciones $consolidacion,
        private PlanCompras $plan,
        private RegionesCoordinacion $regiones,
        private DetalleConsolidado $detalle,
    ) {}

    public function index(Request $request)
    {
        $filtro = $request->validate(['estado' => ['nullable', 'in:borrador,pendientes,correccion,aprobado,cerrado']])['estado'] ?? null;
        $consulta = $this->acceso->consolidados($request->user(), $request->boolean('xlsx') ? 'exportar_consolidados' : 'consolidados')->with('coordinador')->latest('id');
        if ($filtro !== null) {
            $consulta->whereIn('estado', $filtro === 'pendientes' ? ['enviado', 'revision'] : [$filtro]);
        }
        if ($request->boolean('xlsx')) {
            $id = $request->validate(['consolidado_id' => ['nullable', 'integer']])['consolidado_id'] ?? null;
            if ($id !== null) {
                $consulta->whereKey($id)->firstOrFail();
            }

            return app(ExportacionesOperativas::class)->consolidados($consulta);
        }
        $consolidados = $consulta->paginate(30)->withQueryString();

        return view('flujo.consolidados', compact('consolidados'));
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'crear_consolidados');
        $this->acceso->autorizar($usuario, 'enviar_consolidados');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'], 'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'coordinador_id' => ['nullable', 'integer', 'exists:users,id'],
            'inspecciones' => ['required', 'array', 'min:1', 'max:200'], 'inspecciones.*' => ['required', 'integer', 'distinct'],
        ]);

        $consolidado = DB::transaction(function () use ($usuario, $datos) {
            abort_unless($this->acceso->inspecciones($usuario)->whereKey($datos['inspecciones'])->count() === count($datos['inspecciones']), 403);
            $inspecciones = $this->consolidacion->bloquearDisponibles($datos['inspecciones']);
            if ($inspecciones->contains(fn ($fila) => $fila->fecha->toDateString() < $datos['desde'] || $fila->fecha->toDateString() > $datos['hasta'])) {
                throw ValidationException::withMessages(['inspecciones' => 'Selecciona inspecciones dentro del período indicado.']);
            }
            $coordinador = $this->regiones->resolver($inspecciones->pluck('region_id'), bloquear: true, eleccion: $datos['coordinador_id'] ?? null);
            return $this->consolidacion->generar($usuario, $coordinador, $inspecciones, $datos['nombre'], $datos['desde'], $datos['hasta']);
        }, 3);

        return redirect()->route('flujo.consolidados.show', $consolidado)->with('success', 'Consolidado enviado a coordinación.');
    }

    public function show(Request $request, int $consolidado)
    {
        $usuario = $request->user();
        $consolidado = $this->acceso->consolidados($usuario)->with(['corte', 'coordinador', 'aprobador', 'inspecciones.supervisor',
            'inspecciones.colaborador', 'inspecciones.region', 'inspecciones.lineas', 'participantes.colaborador', 'participantes.region',
            'participantes.necesidades' => fn ($q) => $q->with('producto')->withCount('asignaciones'),
            'lineas.producto', 'eventos.user'])->findOrFail($consolidado);
        $acciones = collect(self::TRANSICIONES)->filter(fn ($regla) => in_array($consolidado->estado, $regla[1], true)
            && $this->acceso->puede($usuario, $regla[0])
            && $this->acceso->consolidados($usuario, $regla[0])->whereKey($consolidado->id)->exists())->keys();
        $puedeAgregar = $acciones->contains('ajustar');
        $puedeAgregar = $puedeAgregar && $this->acceso->puede($usuario, 'agregar_colaboradores')
            && $this->acceso->consolidados($usuario, 'agregar_colaboradores')->whereKey($consolidado->id)->exists();
        $candidatos = collect();
        if ($acciones->contains('reasignar') || ($acciones->contains('enviar') && $consolidado->estado === 'borrador')) {
            $ids = $consolidado->inspecciones->pluck('region_id')->merge($consolidado->participantes->pluck('region_id'));
            $opciones = $this->regiones->opciones($ids->all());
            $candidatos = $opciones->first()?->filter(fn ($usuario) => $opciones->every(fn ($grupo) => $grupo->contains('id', $usuario->id))) ?? collect();
        }
        $editar = $acciones->contains('ajustar');
        $productos = $editar ? Producto::orderBy('nombre')->get(['codigo', 'nombre']) : collect();
        $vista = $request->query('vista') === 'resumen' ? 'resumen' : 'colaborador';
        $puedeCompras = $this->acceso->puede($usuario, 'compras');
        $constancias = $puedeCompras ? $consolidado->constancias : collect();
        $seguimiento = $puedeCompras && in_array($consolidado->estado, ['aprobado', 'cerrado'], true);
        $facturas = $seguimiento ? $this->plan->facturas(collect([$consolidado]))->get($consolidado->id, collect()) : collect();
        $facturasPorProducto = $seguimiento ? DB::table('compra_detalle_consolidado as d')
            ->join('consolidado_lineas as l', 'l.id', '=', 'd.consolidado_linea_id')
            ->join('compra_detalles as cd', 'cd.id', '=', 'd.compra_detalle_id')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')->where('l.consolidado_id', $consolidado->id)
            ->orderBy('c.fecha_compra')->orderBy('c.id')
            ->get(['l.producto_codigo', 'c.id', 'c.no_factura', 'd.cantidad'])->groupBy('producto_codigo') : collect();

        $puedeCerrarCompra = $acciones->contains('cerrar') && $this->plan->puedeCerrar($consolidado);

        return view('flujo.consolidado', compact('consolidado', 'acciones', 'productos', 'vista', 'editar', 'facturas',
            'facturasPorProducto', 'constancias', 'seguimiento', 'puedeCerrarCompra', 'puedeAgregar', 'candidatos'));
    }

    public function colaboradores(Request $request, int $consolidado)
    {
        $usuario = $request->user();
        $fila = $this->acceso->consolidados($usuario, 'agregar_colaboradores')->findOrFail($consolidado);
        abort_unless(in_array($fila->estado, ['enviado', 'revision'], true), 404);
        $q = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q'];

        return response()->json(Colaborador::with('region')->whereIn('region_id', $this->acceso->regionesRevision($usuario, $fila, 'agregar_colaboradores'))
            ->where('estado', 'Activo')->where(fn ($consulta) => $consulta->where('nombre', 'like', '%'.$q.'%')->orWhere('codigo', 'like', '%'.$q.'%'))
            ->orderBy('nombre')->limit(20)->get(['codigo', 'nombre', 'region_id'])
            ->map(fn ($persona) => ['codigo' => $persona->codigo, 'nombre' => $persona->nombre, 'region' => $persona->region->nombre]))
            ->header('Cache-Control', 'private, no-store');
    }

    public function transicion(Request $request, int $consolidado, string $accion)
    {
        abort_if($accion === 'corregir', 403);
        [$permiso, $estados, $destino] = self::TRANSICIONES[$accion] ?? abort(404);
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, $permiso);
        $cantidadEntera = ['integer', 'min:0', Rule::anyOf(['integer:strict', 'string'])];
        $datos = $request->validate([
            'nota' => [in_array($accion, ['devolver', 'retirar', 'reabrir', 'reasignar', 'ajustar'], true) ? 'required' : 'nullable', 'string', 'max:4000'],
            'coordinador_id' => ['nullable', 'integer', 'exists:users,id'],
            'participante_id' => ['nullable', 'integer'],
            'colaborador_codigo' => ['nullable', 'string', 'max:255'],
            'cantidades' => ['nullable', 'array', 'max:1000'],
            'cantidades.*' => ['required', ...$cantidadEntera],
            'producto_codigo' => ['nullable', 'string', 'exists:productos,codigo', 'required_with:cantidad_nueva'],
            'cantidad_nueva' => ['nullable', 'required_with:producto_codigo', ...$cantidadEntera],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($usuario, $consolidado, $accion, $permiso, $estados, $destino, $datos) {
            $fila = $this->acceso->consolidados($usuario, $permiso)->whereKey($consolidado)->lockForUpdate()->firstOrFail();
            $version = (int) $fila->eventos()->max('id');
            if (!in_array($fila->estado, $estados, true) || (int) $datos['version'] !== $version) {
                throw ValidationException::withMessages(['estado' => 'El consolidado cambió. Abre el detalle y revisa la versión vigente.']);
            }
            if ($accion === 'reabrir' && ($fila->constancias()->exists() ||
                DB::table('compra_consolidado')->where('consolidado_id', $fila->id)->exists() ||
                $fila->participantes()->whereHas('necesidades.asignaciones')->exists())) {
                throw ValidationException::withMessages(['estado' => 'Una constancia, compra o asignación vinculada conserva esta aprobación.']);
            }
            if ($accion === 'cerrar' && !$this->plan->puedeCerrar($fila, bloquear: true)) {
                throw ValidationException::withMessages(['estado' => 'Completa la cobertura y concilia las facturas antes de cerrar compras. Si usarás existencias de central, guárdalas en el plan.']);
            }
            $cambios = ['estado' => ['antes' => $fila->estado, 'nuevo' => $destino ?? $fila->estado]];
            if (in_array($accion, ['enviar', 'revisar', 'preparar_detalle', 'ajustar', 'aprobar', 'devolver', 'reasignar'], true)) {
                $ids = $fila->inspecciones()->pluck('inspecciones.region_id')->merge($fila->participantes()->pluck('region_id'));
                $puedeCambiar = $accion === 'reasignar' || ($accion === 'enviar' && $fila->estado === 'borrador');
                $eleccion = $puedeCambiar ? ($datos['coordinador_id'] ?? null) : (int) $fila->coordinador_id;
                try {
                    $elegido = $this->regiones->resolver($ids, bloquear: true, eleccion: $eleccion);
                } catch (ValidationException $e) {
                    if (!$puedeCambiar) {
                        throw ValidationException::withMessages(['estado' => 'La responsabilidad regional cambió. Administración debe reasignar el consolidado.']);
                    }
                    throw $e;
                }
                if ((int) $elegido->id !== (int) $fila->coordinador_id) {
                    $cambios['coordinador'] = ['antes' => $fila->coordinador_id, 'nuevo' => $elegido->id];
                    $fila->coordinador_id = $elegido->id;
                } elseif ($accion === 'reasignar') {
                    throw ValidationException::withMessages(['estado' => 'El consolidado ya tiene el responsable regional vigente.']);
                }
                if (in_array($permiso, ['revisar', 'ajustar', 'aprobar'], true)) {
                    $regiones = $this->acceso->regionesRevision($usuario, $fila, $permiso);
                }
                if (in_array($accion, ['ajustar', 'aprobar', 'preparar_detalle'], true)) {
                    $this->detalle->inicializar($fila);
                }
                if ($accion === 'ajustar') {
                    if (empty($datos['participante_id']) && blank($datos['colaborador_codigo'] ?? null)) {
                        throw ValidationException::withMessages(['colaborador_codigo' => 'Selecciona un colaborador para registrar el ajuste.']);
                    }
                    if (empty($datos['participante_id'])) {
                        $this->acceso->consolidados($usuario, 'agregar_colaboradores')->whereKey($fila->id)->firstOrFail();
                        $regiones = array_intersect($regiones, $this->acceso->regionesRevision($usuario, $fila, 'agregar_colaboradores'));
                    }
                    $cambios['productos'] = $this->detalle->ajustar($fila, $usuario, $datos, collect($regiones));
                } elseif (filled($datos['cantidades'] ?? null) || filled($datos['producto_codigo'] ?? null)) {
                    throw ValidationException::withMessages(['cantidades' => 'Guarda los ajustes por colaborador antes de cambiar el estado.']);
                }
            }
            if ($destino !== null) {
                $fila->estado = $destino;
            }
            if ($accion === 'aprobar') {
                $fila->aprobado_por = $usuario->id;
                $fila->aprobado_en = now();
            } elseif ($accion === 'reabrir') {
                $fila->aprobado_por = null;
                $fila->aprobado_en = null;
                $fila->plan_compra = null;
            }
            $fila->save();
            $this->consolidacion->evento($fila, $usuario, $accion, $datos['nota'] ?? null, $cambios);
        }, 3);

        return back()->with('success', $accion === 'cerrar' ? 'Compras cerradas. Las entregas se registran por separado.' : 'Consolidado actualizado.');
    }
}
