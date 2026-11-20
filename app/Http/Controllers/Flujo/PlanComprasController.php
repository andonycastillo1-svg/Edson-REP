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
        $datos = $request->validate($this->reglas());
        $disponibles = Consolidado::where('estado', 'aprobado')->with('coordinador')->orderBy('nombre')->get();
        $seleccion = isset($datos['consolidados']) ? $this->plan->aprobados($datos['consolidados']) : $disponibles;
        $lineas = $bodega ? $this->plan->cantidades($seleccion, $bodega->id) : collect();
        $constancias = ConstanciaGerencia::with('consolidados')->latest('id')->paginate(15);

        return view('flujo.plan-compras', compact('bodega', 'disponibles', 'seleccion', 'lineas', 'constancias'));
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'compras');
        $bodega = $this->acceso->central($usuario) ?? abort(403);
        $datos = $request->validate([
            'consolidados' => ['required', 'array', 'min:1', 'max:200'], 'medio' => ['required', 'in:Correo,WhatsApp'],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'autorizo' => ['required', 'string', 'max:160'], 'referencia' => ['required', 'string', 'max:4000'],
            'documento' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'propuestas' => ['nullable', 'array', 'max:1000'], 'propuestas.*' => ['nullable', 'integer', 'between:0,400000000'],
        ] + $this->reglas());
        $archivo = $request->file('documento');
        $ruta = $archivo ? DocumentoPrivado::guardar($archivo, 'constancias-gerencia') : null;
        try {
            DB::transaction(function () use ($usuario, $bodega, $datos, $archivo, $ruta) {
                $consolidados = $this->plan->aprobados($datos['consolidados'], bloquear: true);
                $lineas = $this->plan->cantidades($consolidados, $bodega->id);
                $indices = $lineas->map(fn ($fila) => bin2hex($fila['codigo']))->all();
                if (array_diff(array_keys($datos['propuestas'] ?? []), $indices) !== []) {
                    throw ValidationException::withMessages(['propuestas' => 'Vuelve a calcular el plan con los consolidados vigentes.']);
                }
                $plan = $lineas->map(fn ($fila, $i) => $fila + ['propuesta' => (int) ($datos['propuestas'][bin2hex($fila['codigo'])] ?? $fila['sugerida'])])->values()->all();
                $constancia = ConstanciaGerencia::create([
                    'user_id' => $usuario->id, 'medio' => $datos['medio'], 'fecha' => $datos['fecha'], 'autorizo' => $datos['autorizo'],
                    'referencia' => $datos['referencia'], 'plan' => $plan, 'ruta' => $ruta,
                    'nombre_archivo' => $archivo ? Str::limit($archivo->getClientOriginalName(), 240, '') : null,
                ]);
                $constancia->consolidados()->attach($consolidados->modelKeys());
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

    private function reglas(): array
    {
        return [
            'consolidados' => ['nullable', 'array', 'max:200'],
            'consolidados.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
