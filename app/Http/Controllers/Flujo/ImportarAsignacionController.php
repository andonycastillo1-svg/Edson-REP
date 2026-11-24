<?php

namespace App\Http\Controllers\Flujo;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\ImportacionAsignacion;
use App\Models\Producto;
use App\Services\ExcelAsignaciones;
use App\Services\FlujoAcceso;
use App\Services\HojaAsignacion;
use ZipArchive;
use App\Services\RegistrarAsignaciones;
use App\Services\RequerimientosAsignacion;
use App\Sistema\Ajustes;
use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use App\Sistema\ValoresCampos;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportarAsignacionController extends Controller
{
    public function __construct(private FlujoAcceso $acceso, private ExcelAsignaciones $excel) {}

    public function index(Request $request, RequerimientosAsignacion $requerimientos)
    {
        $bodega = $this->bodega($request);
        $importaciones = ImportacionAsignacion::where('user_id', $request->user()->id)->whereNotNull('confirmada_en')
            ->latest('confirmada_en')->paginate(15);

        $consolidadosOrigen = $requerimientos->disponibles($request->user())->latest('id')->get(['id', 'nombre']);

        return view('flujo.importar', compact('bodega', 'importaciones', 'consolidadosOrigen'));
    }

    public function plantilla(Request $request)
    {
        $this->bodega($request);

        return response()->streamDownload(fn () => $this->excel->plantilla()->save('php://output'),
            'plantilla-asignaciones.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function previsualizar(Request $request, RequerimientosAsignacion $requerimientos)
    {
        $bodega = $this->bodega($request);
        $datos = $request->validate(['excel' => ['required', 'file', 'mimes:xlsx', 'max:4096'], 'consolidado_id' => ['nullable', 'integer']]);
        $archivo = $datos['excel'];
        $consolidado = isset($datos['consolidado_id']) ? $requerimientos->verificar($request->user(), (int) $datos['consolidado_id']) : null;
        [$filas, $errores] = $this->excel->leer($archivo->getRealPath());
        [$filas, $errores] = $this->excel->validar($filas, $bodega, $request->user(), $errores);
        if ($errores !== []) {
            return response()->view('flujo.importacion', compact('filas', 'errores', 'bodega'), 422);
        }

        $importacion = ImportacionAsignacion::create([
            'id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'bodega_id' => $bodega->id, 'filas' => $filas,
            'consolidado_id' => $consolidado?->id,
        ]);

        return redirect()->route('flujo.importar.show', $importacion);
    }

    public function show(Request $request, string $importacion)
    {
        $bodega = $this->bodega($request);
        $importacion = ImportacionAsignacion::with('consolidado')->where('user_id', $request->user()->id)->findOrFail($importacion);
        $filas = $importacion->filas;

        return view('flujo.importacion', compact('importacion', 'filas', 'bodega'));
    }

    public function confirmar(Request $request, string $importacion, RegistrarAsignaciones $registro, ValoresCampos $campos)
    {
        $bodega = $this->bodega($request);
        $usuario = $request->user();
        try {
            DB::transaction(function () use ($usuario, $bodega, $importacion, $registro, $campos) {
                $lote = ImportacionAsignacion::where('user_id', $usuario->id)->whereKey($importacion)->lockForUpdate()->firstOrFail();
                if ($lote->confirmada_en !== null) {
                    return;
                }
                DB::table('sistema_ajustes')->where('clave', 'general')->lockForUpdate()->first();
                app(Ajustes::class)->refrescar('general');
                if ((int) $lote->bodega_id !== (int) $bodega->id || $this->acceso->central($usuario)?->id !== $bodega->id) {
                    throw ValidationException::withMessages(['excel' => 'La bodega central cambió. Genera una nueva vista previa.']);
                }
                $filas = collect($lote->filas);
                Colaborador::whereKey($filas->pluck('colaborador_codigo')->unique()->values()->all())->orderBy('codigo')->lockForUpdate()->get();
                Producto::whereKey($filas->pluck('producto_codigo')->unique()->values()->all())->orderBy('codigo')->lockForUpdate()->get();
                $contenido = $filas->sortBy(['colaborador_codigo', 'producto_codigo'])->values();
                $lote->update(['huella_confirmada' => hash('sha256', $bodega->id.'|'.$contenido->toJson())]);
                [$filas, $errores] = $this->excel->validar($lote->filas, $bodega, $usuario);
                if ($errores !== []) {
                    throw ValidationException::withMessages(collect($errores)->mapWithKeys(fn ($lista, $fila) =>
                        ['fila_'.$fila => array_map(fn ($mensaje) => 'Fila '.$fila.' · '.$mensaje, $lista)])->all());
                }
                $ordenadas = collect($filas)->sortBy(['colaborador_codigo', 'producto_codigo'])->values();
                $valores = $campos->predeterminadosAutomaticos('asignaciones');
                $entregas = [];
                foreach ($ordenadas->groupBy('colaborador_codigo') as $codigo => $grupo) {
                    $fecha = $this->excel->salida($grupo->first()['fecha']);
                    $datos = ['colaborador_codigo' => (string) $codigo, 'fecha' => $fecha->toDateTimeString(), 'consolidado_id' => $lote->consolidado_id];
                    $items = $grupo->map(fn ($item) => $item + ['bodega_id' => $bodega->id]);
                    $entrega = $registro->registrar($usuario, $datos, $items, $valores);
                    $entregas[] = ['codigo' => (string) $codigo, 'grupo' => $entrega->id, 'productos' => $grupo->count()];
                }
                $lote->update(['entregas' => $entregas, 'confirmada_en' => now()]);
                app(Auditoria::class)->registrar('flujo', 'confirmar_importacion', $lote->id, null, ['entregas' => count($entregas)]);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'huella_confirmada')) {
                throw ValidationException::withMessages(['excel' => 'Este conjunto de asignaciones ya fue importado.']);
            }
            throw $e;
        }

        return redirect()->route('flujo.importar.show', $importacion)->with('success', 'Importación registrada.');
    }

    public function hojas(Request $request, string $importacion, HojaAsignacion $hojas)
    {
        $this->bodega($request);
        $usuario = $request->user();
        abort_unless(app(Permisos::class)->permite($usuario, 'asignaciones.exportar'), 403);
        $lote = ImportacionAsignacion::where('user_id', $usuario->id)->whereNotNull('confirmada_en')->findOrFail($importacion);
        $ruta = tempnam(sys_get_temp_dir(), 'edson-hojas-');
        abort_if($ruta === false, 500);
        try {
            $zip = new ZipArchive();
            if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo preparar el archivo de hojas.');
            }
            $zip->addFile(public_path('img/logo.png'), 'logo.png');
            foreach ($lote->entregas as $indice => $entrega) {
                $nombre = sprintf('%03d-%s.html', $indice + 1, Str::limit(Str::slug($entrega['codigo']), 120, ''));
                $zip->addFromString($nombre, $hojas->vista($usuario, $entrega['grupo'], portatil: true)->render());
            }
            if (!$zip->close()) {
                throw new \RuntimeException('No se pudo cerrar el archivo de hojas.');
            }
        } catch (\Throwable $e) {
            if (isset($zip) && $zip->filename) {
                $zip->close();
            }
            unlink($ruta);

            throw $e;
        }

        return response()->download($ruta, 'hojas-asignacion.zip', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }

    private function bodega(Request $request): Bodega
    {
        $usuario = $request->user();
        $this->acceso->autorizar($usuario, 'importar');
        abort_unless(app(Permisos::class)->permite($usuario, 'asignaciones.crear'), 403);

        return $this->acceso->central($usuario) ?? abort(403);
    }
}
