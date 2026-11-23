<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\Consolidado;
use App\Models\Inspeccion;
use App\Models\Producto;
use App\Models\User;
use App\Services\FlujoAcceso;
use App\Services\PlanCompras;
use App\Sistema\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsolidadoController extends Controller
{
    private const TRANSICIONES = [
        'enviar' => ['consolidar', ['borrador', 'correccion'], 'enviado'],
        'revisar' => ['revisar', ['enviado'], 'revision'],
        'ajustar' => ['revisar', ['enviado', 'revision'], 'revision'],
        'corregir' => ['consolidar', ['borrador', 'correccion'], null],
        'aprobar' => ['revisar', ['enviado', 'revision'], 'aprobado'],
        'devolver' => ['revisar', ['enviado', 'revision'], 'correccion'],
        'retirar' => ['consolidar', ['enviado', 'revision'], 'correccion'],
        'reabrir' => ['reabrir', ['aprobado'], 'correccion'],
        'cerrar' => ['consolidar', ['aprobado'], 'cerrado'],
    ];

    public function __construct(private FlujoAcceso $acceso, private Auditoria $auditoria, private PlanCompras $plan) {}

    public function index(Request $request)
    {
        $filtro = $request->validate(['estado' => ['nullable', 'in:borrador,pendientes,correccion,aprobado,cerrado']])['estado'] ?? null;
        $consulta = $this->acceso->consolidados($request->user())->with('coordinador')->latest('id');
        if ($filtro !== null) {
            $consulta->whereIn('estado', $filtro === 'pendientes' ? ['enviado', 'revision'] : [$filtro]);
        }
        $consolidados = $consulta->paginate(30)->withQueryString();

        return view('flujo.consolidados', compact('consolidados'));
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'consolidar');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'], 'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'inspecciones' => ['required', 'array', 'min:1', 'max:200'], 'inspecciones.*' => ['required', 'integer', 'distinct'],
        ]);

        $consolidado = DB::transaction(function () use ($usuario, $datos) {
            $inspecciones = Inspeccion::whereKey($datos['inspecciones'])->orderBy('id')->lockForUpdate()->get();
            if ($inspecciones->count() !== count($datos['inspecciones']) ||
                DB::table('consolidado_inspeccion')->whereIn('inspeccion_id', $inspecciones->modelKeys())->exists()) {
                throw ValidationException::withMessages(['inspecciones' => 'Alguna inspección ya pertenece a otro consolidado o no existe.']);
            }
            $supervisores = User::deAmbito(3)->whereKey($inspecciones->pluck('supervisor_id')->unique()->values()->all())
                ->orderBy('users.id')->lockForUpdate()->get()->keyBy('id');
            $equipos = $inspecciones->map(fn ($fila) => $supervisores->get($fila->supervisor_id)?->coordinador_id)->unique();
            $coordinador = $equipos->first();
            if ($equipos->count() !== 1 || !User::deAmbito(5)->whereKey((int) $coordinador)->exists()
                || $inspecciones->contains(fn ($fila) => $fila->fecha->toDateString() < $datos['desde'] || $fila->fecha->toDateString() > $datos['hasta'])) {
                throw ValidationException::withMessages(['inspecciones' => 'Incluye inspecciones del mismo equipo activo y dentro del período.']);
            }
            $fila = Consolidado::create([
                'nombre' => $datos['nombre'], 'desde' => $datos['desde'], 'hasta' => $datos['hasta'],
                'estado' => 'borrador', 'coordinador_id' => $coordinador, 'operador_id' => $usuario->id,
            ]);
            $fila->inspecciones()->attach($inspecciones->modelKeys());
            $totales = DB::table('inspeccion_lineas')->whereIn('inspeccion_id', $inspecciones->modelKeys())
                ->select('producto_codigo')->selectRaw('SUM(cantidad) AS total')->groupBy('producto_codigo')->get();
            foreach ($totales as $total) {
                $fila->lineas()->create(['producto_codigo' => $total->producto_codigo, 'original' => $total->total, 'cantidad' => $total->total]);
            }
            $this->evento($fila, $usuario, 'crear', null, ['inspecciones' => $inspecciones->modelKeys()]);

            return $fila;
        }, 3);

        return redirect()->route('flujo.consolidados.show', $consolidado)->with('success', 'Consolidado creado.');
    }

    public function show(Request $request, int $consolidado)
    {
        $usuario = $request->user();
        $consolidado = $this->acceso->consolidados($usuario)->with('coordinador', 'aprobador', 'inspecciones.supervisor',
            'inspecciones.colaborador', 'inspecciones.region', 'lineas.producto', 'eventos.user')->findOrFail($consolidado);
        $acciones = collect(self::TRANSICIONES)->filter(fn ($regla) => $this->acceso->puede($usuario, $regla[0])
            && in_array($consolidado->estado, $regla[1], true))->keys();
        $productos = $acciones->intersect(['ajustar', 'corregir'])->isNotEmpty()
            ? Producto::orderBy('nombre')->get(['codigo', 'nombre']) : collect();
        $puedeCompras = $this->acceso->puede($usuario, 'compras');
        $constancias = $puedeCompras ? $consolidado->constancias : collect();
        $seguimiento = $puedeCompras && in_array($consolidado->estado, ['aprobado', 'cerrado'], true);
        $facturas = $seguimiento ? $this->plan->facturas(collect([$consolidado]))->get($consolidado->id, collect()) : collect();

        return view('flujo.consolidado', compact('consolidado', 'acciones', 'productos', 'facturas', 'constancias', 'seguimiento'));
    }

    public function transicion(Request $request, int $consolidado, string $accion)
    {
        [$permiso, $estados, $destino] = self::TRANSICIONES[$accion] ?? abort(404);
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, $permiso);
        $datos = $request->validate([
            'nota' => [in_array($accion, ['devolver', 'retirar', 'reabrir'], true) ? 'required' : 'nullable', 'string', 'max:4000'],
            'cantidades' => ['nullable', 'array', 'max:1000'],
            'cantidades.*' => ['required', 'integer', 'between:0,2000000'],
            'producto_codigo' => ['nullable', 'string', 'exists:productos,codigo', 'required_with:cantidad_nueva'],
            'cantidad_nueva' => ['nullable', 'integer', 'between:1,2000000', 'required_with:producto_codigo'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($usuario, $consolidado, $accion, $estados, $destino, $datos) {
            $fila = $this->acceso->consolidados($usuario)->whereKey($consolidado)->lockForUpdate()->firstOrFail();
            $version = (int) $fila->eventos()->max('id');
            if (!in_array($fila->estado, $estados, true) || (int) $datos['version'] !== $version) {
                throw ValidationException::withMessages(['estado' => 'El consolidado cambió. Abre el detalle y revisa la versión vigente.']);
            }
            if ($accion === 'reabrir' && ($fila->constancias()->exists() ||
                DB::table('compra_consolidado')->where('consolidado_id', $fila->id)->exists())) {
                throw ValidationException::withMessages(['estado' => 'Una constancia o compra vinculada conserva esta aprobación.']);
            }
            if ($accion === 'cerrar') {
                $seleccion = collect([$fila]);
                if ($this->plan->conciliacionesPendientes($seleccion, bloquear: true)
                    || $this->plan->avance($seleccion, bloquear: true)->contains(fn ($linea) => $linea['pendiente'] > 0 || $linea['sin_cobertura'] > 0)) {
                    throw ValidationException::withMessages(['estado' => 'Completa la cobertura aprobada y concilia las facturas históricas antes de cerrar.']);
                }
            }
            if ($accion === 'enviar') {
                $inspecciones = $fila->inspecciones()->get();
                $coordinadores = User::deAmbito(3)->whereKey($inspecciones->pluck('supervisor_id')->unique()->values()->all())
                    ->orderBy('users.id')->lockForUpdate()->get()->keyBy('id');
                $equipos = $inspecciones->map(fn ($i) => $coordinadores->get($i->supervisor_id)?->coordinador_id)->unique();
                if ($equipos->count() !== 1 || !User::deAmbito(5)->whereKey((int) $equipos->first())->exists()) {
                    throw ValidationException::withMessages(['estado' => 'Las inspecciones deben pertenecer a un mismo equipo con coordinador activo.']);
                }
                $anterior = $fila->coordinador_id;
                $fila->coordinador_id = $equipos->first();
            }
            $cambios = ['estado' => ['antes' => $fila->estado, 'nuevo' => $destino ?? $fila->estado]];
            if ($accion === 'enviar' && (int) $anterior !== (int) $fila->coordinador_id) {
                $cambios['coordinador'] = ['antes' => $anterior, 'nuevo' => $fila->coordinador_id];
            }
            if (in_array($accion, ['ajustar', 'corregir', 'aprobar'], true)) {
                $cambios['productos'] = $this->ajustes($fila, $datos);
                if ($cambios['productos'] !== [] && blank($datos['nota'] ?? null)) {
                    throw ValidationException::withMessages(['nota' => 'Describe el motivo de los ajustes.']);
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
            }
            $fila->save();
            $this->evento($fila, $usuario, $accion, $datos['nota'] ?? null, $cambios);
        }, 3);

        return back()->with('success', 'Consolidado actualizado.');
    }

    private function ajustes(Consolidado $fila, array $datos): array
    {
        $lineas = $fila->lineas()->get()->keyBy('id');
        $cambios = [];
        foreach ($datos['cantidades'] ?? [] as $id => $cantidad) {
            $linea = $lineas->get($id);
            if (!$linea) {
                throw ValidationException::withMessages(['cantidades' => 'Un producto no pertenece a este consolidado.']);
            }
            if ((int) $linea->cantidad !== (int) $cantidad) {
                $cambios[] = ['producto' => $linea->producto_codigo, 'antes' => $linea->cantidad, 'nuevo' => (int) $cantidad];
                $linea->update(['cantidad' => (int) $cantidad]);
            }
        }
        if (filled($datos['producto_codigo'] ?? null)) {
            $codigo = Producto::findOrFail($datos['producto_codigo'])->codigo;
            if ($lineas->contains('producto_codigo', $codigo)) {
                throw ValidationException::withMessages(['producto_codigo' => 'Ajusta la cantidad del producto que ya está en el consolidado.']);
            }
            $fila->lineas()->create(['producto_codigo' => $codigo, 'original' => 0, 'cantidad' => $datos['cantidad_nueva']]);
            $cambios[] = ['producto' => $codigo, 'antes' => 0, 'nuevo' => (int) $datos['cantidad_nueva']];
        }

        return $cambios;
    }

    private function evento(Consolidado $fila, User $usuario, string $accion, ?string $nota, array $cambios): void
    {
        $evento = $fila->eventos()->create(['user_id' => $usuario->id, 'accion' => $accion, 'nota' => $nota, 'cambios' => $cambios]);
        $this->auditoria->registrar('flujo', $accion.'_consolidado', $fila->id, null, ['evento' => $evento->id] + $cambios);
    }
}
