<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Services\FormatosDocumento;
use App\Sistema\Auditoria;
use App\Sistema\Permisos;
use App\Sistema\Seguridad;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FormatoDocumentoController extends Controller
{
    public function __construct(private FormatosDocumento $formatos) {}

    public function index(Request $request)
    {
        abort_unless(app(Permisos::class)->esSuperadministrador($request->user()), 403);
        $tipo = $request->query('tipo', 'asignacion');
        abort_unless(is_string($tipo) && isset(FormatosDocumento::TIPOS[$tipo]), 404);
        $vigente = $this->formatos->vigente($tipo);
        $formato = $this->formatos->fusionar($tipo, $vigente->configuracion);
        $campos = $this->formatos->campos($tipo);
        $versiones = DB::table('documento_formatos')->where('tipo', $tipo)->latest('id')->paginate(10)->withQueryString();

        return view('sistema.formatos-documento', compact('tipo', 'formato', 'versiones', 'vigente', 'campos'));
    }

    public function vistaPrevia(Request $request, string $tipo)
    {
        $datos = $this->validar($request, $tipo);
        $formato = $this->formatos->preparar($tipo, $datos);
        $visible = $formato['campos'];
        $datosMuestra = $tipo === 'traslado'
            ? ['Origen' => 'Bodega Central Demo', 'Destino' => 'Bodega Norte Demo', 'Estado' => 'Pendiente']
            : ['Código' => 'DEMO-014', 'Colaborador' => 'Ana Campo Demo'];
        $datosMuestra += ['Fecha' => now()->format('d/m/Y H:i'),
            ...(in_array('region', $visible, true) ? ['Región' => 'Norte Demo'] : []),
            ...(in_array('puesto', $visible, true) ? ['Puesto' => 'Técnica de campo'] : []),
            ...(in_array('recepcion', $visible, true) ? ['Fecha de recepción' => now()->format('d/m/Y H:i')] : []),
            ...(in_array('observaciones', $visible, true) ? ['Observaciones' => 'Información de muestra'] : []),
        ];
        $columnas = ['Código', 'Producto', 'Cantidad'];
        $articulo = ['BOTA-DEMO', 'Bota de seguridad', 2];
        if ($tipo === 'devolucion') {
            $columnas[] = 'Condición';
            $articulo[] = 'Buen estado';
        }
        if (in_array('vida', $visible, true)) {
            $columnas[] = 'Vida útil al entregar';
            $articulo[] = '60 días';
        }
        if (in_array('valor', $visible, true)) {
            $columnas[] = 'Costo unitario';
            $articulo[] = 'Q 55.00';
        }
        $muestra = view('documentos.hoja', [
            'formato' => $formato, 'referencia' => 'Muestra · Datos ficticios',
            'datos' => $datosMuestra, 'columnas' => $columnas, 'filas' => [$articulo],
            'firmantes' => ['Responsable Demo', 'Ana Campo Demo'],
        ]);
        if (!$request->boolean('pdf')) {
            return $muestra;
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->setPaper('letter');
        $pdf->loadHtml($muestra->render());
        $pdf->render();

        return response($pdf->output())->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="muestra-'.$tipo.'.pdf"');
    }

    public function publicar(Request $request, string $tipo, Seguridad $seguridad, Auditoria $auditoria)
    {
        $datos = $this->validar($request, $tipo);
        $clave = $request->validate(['current_password' => ['required', 'string', 'max:255']])['current_password'];
        $seguridad->cambiar($request->user(), $clave, function ($actor) use ($tipo, $datos, $auditoria) {
            $antes = $this->formatos->vigente($tipo)->id;
            $id = DB::table('documento_formatos')->insertGetId([
                'tipo' => $tipo, 'configuracion' => json_encode($this->formatos->preparar($tipo, $datos), JSON_THROW_ON_ERROR),
                'publicado_por' => $actor->id, 'created_at' => now(),
            ]);
            $auditoria->registrar('sistema', 'publicar_formato_documento', $tipo, ['version' => $antes], ['version' => $id]);
        });

        return redirect()->route('sistema.documentos', ['tipo' => $tipo])->with('ok', 'Nueva versión publicada.');
    }

    private function validar(Request $request, string $tipo): array
    {
        abort_unless(app(Permisos::class)->esSuperadministrador($request->user()), 403);
        abort_unless(isset(FormatosDocumento::TIPOS[$tipo]), 404);

        return $request->validate([
            'empresa' => ['required', 'string', 'max:160'], 'titulo' => ['required', 'string', 'max:160'],
            'firma_1' => ['required', 'string', 'max:100'], 'firma_2' => ['required', 'string', 'max:100'],
            'encabezado' => ['nullable', 'string', 'max:120'], 'departamento' => ['nullable', 'string', 'max:120'],
            'introduccion' => ['nullable', 'string', 'max:2000'], 'condiciones' => ['nullable', 'string', 'max:6000'],
            'aceptacion' => ['nullable', 'string', 'max:1000'], 'pie' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:max_width=1800,max_height=1800'],
            'restaurar_logo' => ['nullable', 'boolean'], 'distribucion' => ['required', Rule::in(['compacta', 'vertical'])],
            'ancho_logo' => ['required', 'integer', 'between:36,110'],
            'campos' => ['nullable', 'array', 'list', 'max:6'],
            'campos.*' => ['required', 'distinct', Rule::in(array_keys($this->formatos->campos($tipo)))],
        ]);
    }
}
