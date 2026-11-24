<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\MotivoAsignacion;
use App\Models\Producto;
use App\Models\Region;
use App\Services\FlujoAcceso;
use App\Sistema\Auditoria;
use App\Sistema\ValoresCampos;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogosController extends Controller
{
    public function __construct(private FlujoAcceso $acceso, private Auditoria $auditoria) {}

    public function index(Request $request)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $regiones = Region::orderBy('nombre')->get();
        $secciones = DB::table('inspeccion_secciones')->orderBy('orden')->get();
        $articulos = DB::table('inspeccion_articulos')->orderBy('orden')->orderBy('id')->get()->groupBy('seccion');
        $productos = Producto::orderBy('nombre')->get(['codigo', 'nombre']);
        $motivos = MotivoAsignacion::orderBy('id')->get();

        return view('flujo.catalogos', compact('regiones', 'secciones', 'articulos', 'productos', 'motivos'));
    }

    public function producto(Request $request, ValoresCampos $campos)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:productos,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
            'categoria' => ['nullable', 'string', 'max:50'],
            'unidad_medida' => ['nullable', 'string', 'max:50'],
            'vida_util_meses' => ['nullable', 'integer', 'between:1,1200'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
        ]);
        $valores = $campos->preparar($request, 'productos', true);
        try {
            DB::transaction(function () use ($datos, $campos, $valores) {
                $campos->verificarClaveDisponible('productos', $datos['codigo']);
                $producto = Producto::create($datos);
                $campos->guardar('productos', $producto->codigo, $valores);
                $this->auditoria->registrar('productos', 'alta_catalogo_inspeccion', $producto->codigo, null, $datos);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'El código ya existe. Selecciónalo en el artículo.']);
        }

        return back()->with('success', 'Producto creado en el catálogo.');
    }

    public function region(Request $request, ?int $id = null)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('regiones')->ignore($id)],
            'activa' => ['required', 'boolean'],
        ]);

        return $this->guardar('regiones', $id, $datos + ['updated_at' => now()], ['created_at' => now()]);
    }

    public function seccion(Request $request, string $clave)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'], 'orden' => ['required', 'integer', 'between:0,65535'],
        ]);
        DB::transaction(function () use ($clave, $datos) {
            $consulta = DB::table('inspeccion_secciones')->where('clave', $clave);
            $antes = $consulta->lockForUpdate()->first() ?? abort(404);
            $consulta->update($datos);
            $this->auditoria->registrar('flujo', 'configurar_seccion', $clave, (array) $antes, $datos);
        }, 3);

        return back()->with('success', 'Sección guardada.');
    }

    public function articulo(Request $request, ?int $id = null)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $datos = $request->validate([
            'seccion' => ['required', 'string', 'exists:inspeccion_secciones,clave'],
            'etiqueta' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'producto_codigo' => [Rule::requiredIf(fn () => $request->boolean('activo')), 'nullable', 'string',
                'exists:productos,codigo', Rule::unique('inspeccion_articulos')->ignore($id)],
            'orden' => ['required', 'integer', 'between:0,65535'],
            'activo' => ['required', 'boolean'],
        ]);
        if (filled($datos['producto_codigo'] ?? null)) {
            $datos['producto_codigo'] = Producto::findOrFail($datos['producto_codigo'])->codigo;
        }

        // La columna histórica sigue siendo obligatoria al crear un artículo.
        return $this->guardar('inspeccion_articulos', $id, $datos, ['maximo' => 0]);
    }

    public function motivo(Request $request, ?int $id = null)
    {
        $this->acceso->autorizar($request->user(), 'configurar');
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'regex:/^[a-z0-9_]+$/', 'max:45', Rule::unique('motivos_asignacion')->ignore($id)],
            'nombre' => ['required', 'string', 'max:120'],
            'politica' => ['required', Rule::in(array_keys(MotivoAsignacion::POLITICAS))],
            'activo' => ['required', 'boolean'],
        ]);

        return $this->guardar('motivos_asignacion', $id, $datos);
    }

    private function guardar(string $tabla, ?int $id, array $datos, array $alCrear = [])
    {
        DB::transaction(function () use ($tabla, $id, $datos, $alCrear) {
            $consulta = DB::table($tabla)->where('id', $id);
            $antes = $id ? $consulta->lockForUpdate()->first() ?? abort(404) : null;
            if ($antes) {
                $consulta->update($datos);
            } else {
                $id = DB::table($tabla)->insertGetId($datos + $alCrear);
            }
            $this->auditoria->registrar('flujo', 'configurar_'.$tabla, $id, $antes ? (array) $antes : null, $datos);
        }, 3);

        return back()->with('success', 'Configuración guardada.');
    }
}
