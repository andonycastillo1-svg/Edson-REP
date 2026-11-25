<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\CorteRequerimiento;
use App\Models\User;
use App\Services\CortesRequerimientos;
use App\Services\FlujoAcceso;
use App\Services\RegionesCoordinacion;
use App\Sistema\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorteRequerimientoController extends Controller
{
    private const POR_PAGINA = 20;

    public function __construct(
        private FlujoAcceso $acceso,
        private CortesRequerimientos $cortes,
        private RegionesCoordinacion $regiones,
        private Auditoria $auditoria,
    ) {}

    public function index(Request $request)
    {
        $datos = $request->validate(['estado' => ['nullable', Rule::in(array_keys(CorteRequerimiento::ESTADOS))]]);
        $cortes = $this->consulta($request->user())->with('operador')->withCount('inspecciones', 'consolidados')
            ->when($datos['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->latest('id')->paginate(20)->withQueryString();

        return view('flujo.cortes.index', compact('cortes'));
    }

    public function create(Request $request)
    {
        abort_unless($this->acceso->puedeCortes($request->user()), 403);

        return view('flujo.cortes.create');
    }

    public function store(Request $request)
    {
        $usuario = $request->user();
        abort_unless($this->acceso->puedeCortes($usuario), 403);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $corte = DB::transaction(function () use ($usuario, $datos) {
            $corte = CorteRequerimiento::create($datos + ['operador_id' => $usuario->id]);
            $this->auditoria->registrar('flujo', 'crear_corte', $corte->id, null, $datos);

            return $corte;
        });

        return redirect()->route('flujo.cortes.seleccion', $corte);
    }

    public function seleccion(Request $request, int $corte)
    {
        $usuario = $request->user();
        $corte = $this->consulta($usuario)->findOrFail($corte);
        abort_unless((int) $corte->operador_id === (int) $usuario->id, 403);
        if ($corte->estado !== 'borrador') {
            return redirect()->route('flujo.cortes.show', $corte);
        }
        $seleccion = $corte->inspecciones()->select('inspecciones.id')->withExists('consolidados')->get();
        $ids = $seleccion->pluck('id');
        $ocupadas = $seleccion->where('consolidados_exists', true);
        $consultas = [];
        $pendientes = [];
        foreach (['periodo' => false, 'anteriores' => true] as $pagina => $anterior) {
            $consultas[$pagina] = $this->cortes->disponibles($usuario, $corte, $anterior)
                ->with('region', 'colaborador', 'supervisor')->withExists('consolidados')
                ->latest('fecha')->latest('id')->paginate(self::POR_PAGINA, pageName: $pagina);
            $pendientes[$pagina] = $consultas[$pagina]->total() - $ocupadas->where('pivot.anterior', $anterior)->count();
        }
        $visibles = $consultas['periodo']->pluck('id')->merge($consultas['anteriores']->pluck('id'));
        $fueraDePagina = $ids->diff($visibles)->count();
        $regiones = $consultas['periodo']->pluck('region_id')->merge($consultas['anteriores']->pluck('region_id'));
        $coordinadores = $this->regiones->opciones($regiones->all());

        return view('flujo.cortes.seleccion', compact('corte', 'consultas', 'pendientes', 'ids', 'coordinadores', 'fueraDePagina'));
    }

    public function guardarSeleccion(Request $request, int $corte)
    {
        $usuario = $request->user();
        abort_unless($this->acceso->puedeCortes($usuario), 403);
        $maximo = self::POR_PAGINA * 2;
        $datos = $request->validate([
            'visibles' => ['nullable', 'array', 'list', 'max:'.$maximo],
            'visibles.*' => ['required', 'integer', 'min:1', 'distinct'],
            'dentro' => ['nullable', 'array', 'list', 'max:'.self::POR_PAGINA],
            'dentro.*' => ['required', 'integer', 'min:1', 'distinct'],
            'antiguas' => ['nullable', 'array', 'list', 'max:'.self::POR_PAGINA],
            'antiguas.*' => ['required', 'integer', 'min:1', 'distinct'],
            'periodo_actual' => ['nullable', 'integer', 'min:1'],
            'anteriores_actual' => ['nullable', 'integer', 'min:1'],
            'periodo' => ['nullable', 'integer', 'min:1'],
            'anteriores' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($usuario, $corte, $datos) {
            $fila = $this->bloquear($usuario, $corte);
            $this->exigirBorrador($fila);
            $this->cortes->guardarSeleccion($usuario, $fila, array_map('intval', $datos['visibles'] ?? []),
                array_map('intval', $datos['dentro'] ?? []), array_map('intval', $datos['antiguas'] ?? []));
        }, 3);

        if (isset($datos['periodo']) || isset($datos['anteriores'])) {
            return redirect()->route('flujo.cortes.seleccion', [
                'corte' => $corte,
                'periodo' => $datos['periodo'] ?? $datos['periodo_actual'] ?? 1,
                'anteriores' => $datos['anteriores'] ?? $datos['anteriores_actual'] ?? 1,
            ]);
        }

        return redirect()->route('flujo.cortes.show', $corte);
    }

    public function show(Request $request, int $corte)
    {
        $usuario = $request->user();
        $corte = $this->consulta($usuario)->with('operador', 'consolidados.coordinador')->findOrFail($corte);
        $esEditable = $corte->estado === 'borrador' && (int) $corte->operador_id === (int) $usuario->id;
        if ($corte->estado === 'borrador') {
            $resumen = $this->cortes->vistaPrevia($corte);

            return view('flujo.cortes.resumen', compact('corte', 'resumen', 'esEditable'));
        }
        $inspecciones = $corte->inspecciones()->with('region', 'colaborador')->orderBy('fecha')->orderBy('inspecciones.id')->paginate(30);

        return view('flujo.cortes.show', compact('corte', 'inspecciones'));
    }

    public function guardarCoordinacion(Request $request, int $corte)
    {
        $usuario = $request->user();
        abort_unless($this->acceso->puedeCortes($usuario), 403);
        $datos = $request->validate(['responsables' => ['required', 'array'], 'responsables.*' => ['required', 'integer']]);
        DB::transaction(function () use ($usuario, $corte, $datos) {
            $fila = $this->bloquear($usuario, $corte);
            $this->exigirBorrador($fila);
            $opciones = $this->regiones->opciones($fila->inspecciones()->pluck('inspecciones.region_id')->all(), bloquear: true);
            foreach ($datos['responsables'] as $region => $elegido) {
                if (!$opciones->get($region)?->contains('id', (int) $elegido)) {
                    throw ValidationException::withMessages(['responsables' => 'Selecciona solo coordinadores activos de cada región.']);
                }
            }
            $antes = $fila->coordinadores_elegidos ?? [];
            $fila->update(['coordinadores_elegidos' => array_replace($antes, $datos['responsables'])]);
            $this->auditoria->registrar('flujo', 'elegir_coordinacion_corte', $fila->id, $antes, $fila->coordinadores_elegidos);
        }, 3);

        return redirect()->route('flujo.cortes.show', $corte);
    }

    public function confirmar(Request $request, int $corte)
    {
        $usuario = $request->user();
        abort_unless($this->acceso->puedeCortes($usuario), 403);
        $this->acceso->autorizar($usuario, 'crear_consolidados');
        $this->acceso->autorizar($usuario, 'enviar_consolidados');
        $datos = $request->validate(['firma' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        DB::transaction(function () use ($usuario, $corte, $datos) {
            $fila = $this->bloquear($usuario, $corte);
            if ($fila->estado === 'confirmado') {
                return;
            }
            $this->exigirBorrador($fila);
            $this->cortes->confirmar($usuario, $fila, $datos['firma']);
        }, 3);

        return redirect()->route('flujo.cortes.show', $corte)->with('success', 'Consolidados del corte enviados a coordinación.');
    }

    public function cancelar(Request $request, int $corte)
    {
        $usuario = $request->user();
        abort_unless($this->acceso->puedeCortes($usuario), 403);
        DB::transaction(function () use ($usuario, $corte) {
            $fila = $this->bloquear($usuario, $corte);
            if ($fila->estado === 'cancelado') {
                return;
            }
            $this->exigirBorrador($fila);
            $fila->update(['estado' => 'cancelado']);
            $this->auditoria->registrar('flujo', 'cancelar_corte', $fila->id, ['estado' => 'borrador'], ['estado' => 'cancelado']);
        }, 3);

        return redirect()->route('flujo.cortes.show', $corte)->with('success', 'Borrador cancelado. Las inspecciones que sigan pendientes podrán incluirse en otro corte.');
    }

    private function consulta(User $usuario): Builder
    {
        abort_unless($this->acceso->puedeCortes($usuario), 403);

        return CorteRequerimiento::query()->when($usuario->rolOperativo() !== 1, fn ($q) => $q->where(fn ($visibles) =>
            $visibles->where('estado', '!=', 'borrador')->orWhere('operador_id', $usuario->id)));
    }

    private function bloquear(User $usuario, int $corte): CorteRequerimiento
    {
        return CorteRequerimiento::where('operador_id', $usuario->id)->whereKey($corte)->lockForUpdate()->firstOrFail();
    }

    private function exigirBorrador(CorteRequerimiento $corte): void
    {
        if ($corte->estado !== 'borrador') {
            throw ValidationException::withMessages(['estado' => 'El corte ya no está en borrador. Consulta su historial.']);
        }
    }
}
