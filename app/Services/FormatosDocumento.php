<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FormatosDocumento
{
    public const TIPOS = [
        'asignacion' => 'Formato de entrega de equipos y herramientas',
        'traslado' => 'Hoja de traslado',
        'devolucion' => 'Acta de devolución de artículos',
    ];

    public const CAMPOS = [
        'puesto' => 'Puesto', 'region' => 'Región', 'vida' => 'Vida útil', 'recepcion' => 'Fecha de recepción',
        'valor' => 'Valor histórico', 'observaciones' => 'Observaciones',
    ];

    public function campos(string $tipo): array
    {
        return array_intersect_key(self::CAMPOS, array_flip(match ($tipo) {
            'traslado' => ['recepcion', 'observaciones'],
            'devolucion' => ['puesto', 'region'],
            default => array_keys(self::CAMPOS),
        }));
    }

    public const TEXTOS = [
        'empresa' => 'Empresa', 'encabezado' => 'Encabezado', 'titulo' => 'Título', 'departamento' => 'Departamento',
        'introduccion' => 'Introducción', 'condiciones' => 'Condiciones de responsabilidad', 'aceptacion' => 'Aceptación',
        'firma_1' => 'Etiqueta de primera firma', 'firma_2' => 'Etiqueta de segunda firma', 'pie' => 'Pie de página',
    ];

    public function vigente(string $tipo): object
    {
        return DB::table('documento_formatos')->where('tipo', $tipo)->latest('id')->first()
            ?? (object) ['id' => null, 'configuracion' => null];
    }

    public function fusionar(string $tipo, ?string $configuracion): array
    {
        return ($configuracion ? json_decode($configuracion, true, flags: JSON_THROW_ON_ERROR) : []) + $this->base($tipo);
    }

    public function emitir(string $tipo, string $registro): array
    {
        $consulta = DB::table('documento_versiones_emitidas')->where(compact('tipo', 'registro'));
        $version = $consulta->first();
        if (!$version) {
            DB::table('documento_versiones_emitidas')->insertOrIgnore([
                'tipo' => $tipo, 'registro' => $registro, 'formato_id' => $this->vigente($tipo)->id, 'created_at' => now(),
            ]);
            $version = $consulta->first();
        }

        return $this->fusionar($tipo, DB::table('documento_formatos')->where('id', $version->formato_id)->value('configuracion'))
            + ['predeterminado' => $version->formato_id === null];
    }

    public function preparar(string $tipo, array $datos): array
    {
        $anterior = $this->fusionar($tipo, $this->vigente($tipo)->configuracion);
        $logo = $datos['logo'] ?? null;
        $config = collect(self::TEXTOS)->mapWithKeys(fn ($etiqueta, $campo) => [$campo => $datos[$campo] ?? ''])->all();
        $config['campos'] = $datos['campos'] ?? [];
        $config['distribucion'] = $datos['distribucion'];
        $config['ancho_logo'] = (int) $datos['ancho_logo'];
        $config['logo_uri'] = $logo instanceof UploadedFile
            ? 'data:'.$logo->getMimeType().';base64,'.base64_encode($logo->get())
            : (($datos['restaurar_logo'] ?? false) ? $this->base($tipo)['logo_uri'] : $anterior['logo_uri']);

        return $config;
    }

    private function base(string $tipo): array
    {
        $condiciones = <<<'TEXTO'
Por este acto me reconozco responsable ante Grupo Net Solutions de cualquier daño que le ocurra a la herramienta y/o equipo asignado, comprometiéndome a lo siguiente:
• Utilizar la herramienta y equipo única y exclusivamente para fines de los proyectos asignados.
• Notificar inicial e inmediatamente de manera verbal y por escrito vía correo electrónico, adjuntando imágenes según sea el caso, en el plazo de veinticuatro horas cualquier incidente, percance, robo o daño que sufra la herramienta y equipo a mi cargo, junto a denuncia del Ministerio Público cuando aplique.
• Asumir la responsabilidad civil derivada de negligencia, percance, robo o daño.
• Velar porque la herramienta, equipo y uniformes se mantengan en buenas condiciones de funcionamiento durante el tiempo que estén a mi cargo.
• Velar porque los mismos sean almacenados o guardados en lugares seguros mientras no estén siendo utilizados en tareas propias de la labor asignada.
• Permitir que la herramienta y equipo sean objeto de inspecciones, auditorías o revisiones por parte de mis superiores, sin previo aviso, para verificar su estado en cualquier momento.
TEXTO;
        return [
            'empresa' => 'Grupo NetSolutions', 'encabezado' => '', 'titulo' => self::TIPOS[$tipo], 'departamento' => '',
            'introduccion' => '', 'condiciones' => $tipo === 'asignacion' ? $condiciones : '',
            'aceptacion' => $tipo === 'asignacion' ? 'He leído y acepto las condiciones del uso de herramienta y equipo de la empresa.' : '',
            'firma_1' => $tipo === 'devolucion' ? 'Firma de quien registra' : 'Firma de quien entrega',
            'firma_2' => $tipo === 'traslado' ? 'Firma de recepción (destino)' : 'Firma del colaborador',
            'pie' => '', 'campos' => array_keys($this->campos($tipo)), 'ancho_logo' => 64, 'distribucion' => 'compacta',
            'logo_uri' => 'data:'.mime_content_type(public_path('img/logo.png')).';base64,'.base64_encode(file_get_contents(public_path('img/logo.png'))),
        ];
    }
}
