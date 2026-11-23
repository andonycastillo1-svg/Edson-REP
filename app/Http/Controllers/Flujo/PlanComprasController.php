<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\Consolidado;
use App\Models\ConstanciaGerencia;
use App\Services\DocumentoPrivado;
use App\Services\FlujoAcceso;
use App\Services\PlanCompras;
use App\Sistema\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanComprasController extends Controller
{
    public function __construct(private FlujoAcceso $acceso, private PlanCompras $plan) {}

    public function index(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'compras');
        $bodega = $this->acceso->central($usuario);
        $datos = $request->validate($this->reglas() + ['estado' => ['nullable', 'in:aprobado,cerrado']]);
        $estado = $datos['estado'] ?? 'aprobado';
        $disponibles = Consolidado::where('estado', $estado)->with('coordinador')->orderBy('id')->get();
        $seleccion = isset($datos['consolidados'])
            ? $disponibles->whereIn('id', $datos['consolidados'])->values()
            : ($request->boolean('seleccion_manual') ? $disponibles->take(0) : $disponibles->take(200));
        $lineas = $this->plan->avance($seleccion);
        $saldo = $bodega ? $this->plan->saldoCentral($lineas, $bodega->id) : null;
        $lineas = $this->plan->sugerencias(
            $lineas, $estado === 'aprobado' && $bodega
                ? $this->plan->avance(Consolidado::where('estado', 'aprobado')->get(['id', 'plan_compra'])) : collect(),
            $saldo ?? collect(), estimarStock: $estado === 'aprobado'
        );
        $facturas = $this->plan->facturas($seleccion);
        $planificables = $seleccion->reject(fn ($fila) => $facturas->get($fila->id, collect())->contains('distribucion_confirmada_en', null));
        $resumen = $this->plan->resumen($lineas, $facturas, $saldo);
        $constancias = ConstanciaGerencia::with('consolidados')->latest('id')->paginate(15)->withQueryString();

        return view('flujo.plan-compras', compact('bodega', 'estado', 'disponibles', 'seleccion', 'lineas', 'facturas', 'resumen',
            'planificables', 'constancias'));
    }

    public function guardarPlan(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'compras');
        $bodega = $this->acceso->central($usuario) ?? abort(403);
        $datos = $request->validate($this->reglasPlan());

        DB::transaction(function () use ($datos, $bodega, $usuario) {
            [$consolidados, $filas] = $this->prepararPlan($datos, $bodega->id);
            $porConsolidado = $filas->groupBy('consolidado_id');
            foreach ($consolidados as $consolidado) {
                $nuevo = $porConsolidado->get($consolidado->id, collect())->mapWithKeys(fn ($fila) => [
                    $fila['id'] => ['existencia' => $fila['existencia'], 'compra' => $fila['propuesta']],
                ])->all();
                $anterior = $consolidado->plan_compra;
                if ($nuevo === $anterior) {
                    continue;
                }
                $consolidado->plan_compra = $nuevo;
                $consolidado->save();
                $consolidado->eventos()->create(['user_id' => $usuario->id, 'accion' => 'plan_compra',
                    'cambios' => ['antes' => $anterior, 'plan_compra' => $nuevo]]);
                app(Auditoria::class)->registrar('flujo', 'guardar_plan_compra', $consolidado->id, $anterior, $nuevo);
            }
        }, 3);

        return back()->with('success', 'Plan de compra guardado.');
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'compras');
        $bodega = $this->acceso->central($usuario) ?? abort(403);
        $datos = $request->validate([
            'medio' => ['required', 'in:Correo,WhatsApp'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'autorizo' => ['required', 'string', 'max:160'], 'referencia' => ['required', 'string', 'max:4000'],
            'documento' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ] + $this->reglasPlan());
        $archivo = $request->file('documento');
        $ruta = $archivo ? DocumentoPrivado::guardar($archivo, 'constancias-gerencia') : null;
        try {
            DB::transaction(function () use ($usuario, $bodega, $datos, $archivo, $ruta) {
                [$consolidados, $filas, $saldo] = $this->prepararPlan($datos, $bodega->id);
                $plan = $filas->map(fn ($fila) => [
                    'consolidado_id' => $fila['consolidado_id'], 'codigo' => $fila['codigo'], 'producto' => $fila['producto'],
                    'aprobada' => $fila['aprobada'], 'central' => (int) $saldo->get($fila['codigo'], 0),
                    'existencia' => $fila['existencia'], 'propuesta' => $fila['propuesta'],
                ])->values()->all();
                $constancia = ConstanciaGerencia::create([
                    'user_id' => $usuario->id, 'medio' => $datos['medio'], 'fecha' => $datos['fecha'], 'autorizo' => $datos['autorizo'],
                    'referencia' => $datos['referencia'], 'plan' => $plan, 'ruta' => $ruta,
                    'nombre_archivo' => $archivo ? Str::limit($archivo->getClientOriginalName(), 240, '') : null,
                ]);
                $constancia->consolidados()->attach($consolidados->modelKeys());
                Consolidado::whereKey($consolidados->modelKeys())->update(['plan_compra' => null]);
                DB::table('consolidado_planes_compra')->insert($filas->map(fn ($fila) => [
                    'consolidado_linea_id' => $fila['id'], 'constancia_id' => $constancia->id,
                    'existencia_considerada' => $fila['existencia'], 'cantidad_decidida' => $fila['propuesta'],
                ])->all());
                app(Auditoria::class)->registrar('flujo', 'constancia_gerencia', $constancia->id, null,
                    ['consolidados' => $consolidados->modelKeys()]);
            }, 3);
        } catch (\Throwable $e) {
            if ($ruta !== null) {
                DocumentoPrivado::descartar($ruta);
            }

            throw $e;
        }

        return back()->with('success', 'Constancia de autorización externa registrada.');
    }

    public function documento(Request $request, ConstanciaGerencia $constancia)
    {
        $this->acceso->autorizar($request->user(), 'compras');
        abort_unless($constancia->ruta, 404);

        return DocumentoPrivado::responder($constancia->ruta, $constancia->nombre_archivo);
    }

    private function prepararPlan(array $datos, int $bodega): array
    {
        $activos = Consolidado::where('estado', 'aprobado')->orderBy('id')->lockForUpdate()->get(['id', 'plan_compra']);
        $consolidados = $activos->whereIn('id', $datos['consolidados']);
        if ($consolidados->count() !== count($datos['consolidados'])) {
            throw ValidationException::withMessages(['consolidados' => 'Selecciona consolidados aprobados y vigentes.']);
        }
        if ($this->plan->conciliacionesPendientes($consolidados, bloquear: true)) {
            throw ValidationException::withMessages(['propuestas' => 'Concilia primero las facturas históricas de los consolidados que vas a planificar.']);
        }
        $activas = $this->plan->avance($activos, bloquear: true);
        $seleccion = $activas->whereIn('consolidado_id', $consolidados->modelKeys());
        $saldo = $this->plan->saldoCentral($seleccion, $bodega);

        return [$consolidados, $this->plan->validarPlan($seleccion, $activas, $saldo, $datos['propuestas']), $saldo];
    }

    private function reglasPlan(): array
    {
        return [
            'consolidados' => ['required', 'array', 'min:1', 'max:200'],
            'propuestas' => ['required', 'array', 'max:1000'],
            'propuestas.*' => ['required', 'array:existencia,compra'],
            'propuestas.*.existencia' => ['required', 'integer', 'between:0,400000000'],
            'propuestas.*.compra' => ['required', 'integer', 'between:0,400000000'],
        ] + $this->reglas();
    }

    private function reglas(): array
    {
        return [
            'consolidados' => ['nullable', 'array', 'max:200'],
            'consolidados.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
