<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\MotivoAsignacion;
use App\Models\Producto;
use App\Models\User;
use App\Sistema\Presentacion;
use App\Sistema\ValoresCampos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LectorXlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ExcelAsignaciones
{
    private const MAX_FILAS = 500;

    private const COLUMNAS = [
        'colaborador_codigo' => 'Código de colaborador', 'producto_codigo' => 'Código de producto',
        'cantidad' => 'Cantidad', 'fecha' => 'Fecha de asignación', 'aprobado_por' => 'Aprobado por',
        'medio_solicitud' => 'Medio de solicitud', 'motivo_asignacion' => 'Código de motivo', 'observaciones' => 'Observaciones',
    ];

    public function plantilla(): Xlsx
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Asignaciones');
        $hoja->fromArray([array_values(self::COLUMNAS)]);
        $hoja->freezePane('A2');
        foreach (range('A', 'H') as $columna) {
            $hoja->getColumnDimension($columna)->setWidth(26);
        }
        $hoja->getStyle('A1:H1')->getFont()->setBold(true);
        $hoja->getStyle('A2:B501')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $libro->createSheet()->setTitle('Ejemplo')->fromArray([
            array_values(self::COLUMNAS),
            ['COL-001', 'P-001', 1, today()->toDateString(), 'Gerencia', 'Correo', 'desgaste', 'Ejemplo ficticio'],
            ['COL-001', 'P-002', 1, today()->toDateString(), 'Gerencia', 'Correo', '', 'Misma entrega'],
        ]);
        $motivos = $libro->createSheet()->setTitle('Motivos');
        $catalogo = MotivoAsignacion::where('activo', true)->orderBy('id')->get(['codigo', 'nombre']);
        $motivos->fromArray([['Código', 'Motivo']]);
        foreach ($catalogo->values() as $indice => $motivo) {
            $fila = $indice + 2;
            $motivos->setCellValueExplicit('A'.$fila, $motivo->codigo, DataType::TYPE_STRING);
            $motivos->setCellValueExplicit('B'.$fila, $motivo->nombre, DataType::TYPE_STRING);
        }
        if ($catalogo->isNotEmpty()) {
            $libro->addNamedRange(new NamedRange('CodigosMotivo', $motivos, '$A$2:$A$'.($catalogo->count() + 1)));
            $validacion = $hoja->getCell('G2')->getDataValidation();
            $validacion->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                ->setShowErrorMessage(true)->setErrorTitle('Motivo no válido')->setError('Selecciona un código de motivo vigente.')
                ->setFormula1('CodigosMotivo')->setSqref('G2:G501');
        }
        $libro->setActiveSheetIndex(0);

        return new Xlsx($libro);
    }

    public function leer(string $archivo): array
    {
        $this->validarArchivo($archivo);
        $lector = new LectorXlsx();
        $lector->setReadDataOnly(true);
        $lector->setLoadSheetsOnly('Asignaciones');
        $lector->setReadFilter(new class implements IReadFilter {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= 502 && in_array($columnAddress, range('A', 'I'), true);
            }
        });
        try {
            $info = collect($lector->listWorksheetInfo($archivo))->firstWhere('worksheetName', 'Asignaciones');
            if ($info === null || $info['totalRows'] > self::MAX_FILAS + 1 || $info['totalColumns'] > count(self::COLUMNAS)) {
                throw new \RuntimeException('Dimensiones de hoja fuera del formato.');
            }
            $libro = $lector->load($archivo);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['excel' => 'Usa la hoja Asignaciones de la plantilla con hasta 500 filas y ocho columnas.']);
        }

        try {
            $hoja = $libro->getSheetByName('Asignaciones');
            if (!$hoja || $hoja->rangeToArray('A1:H1', null, false, false)[0] !== array_values(self::COLUMNAS)) {
                throw ValidationException::withMessages(['excel' => 'Conserva los encabezados de la plantilla.']);
            }
            $filas = [];
            $errores = [];
            for ($numero = 2; $numero <= $hoja->getHighestDataRow(); $numero++) {
                $fila = [];
                foreach (array_keys(self::COLUMNAS) as $i => $clave) {
                    $celda = $hoja->getCell(chr(65 + $i).$numero);
                    $valor = $celda->getValue();
                    if ($celda->getDataType() === DataType::TYPE_FORMULA || (is_object($valor) && !$valor instanceof \Stringable)) {
                        $errores[$numero][] = 'Usa valores simples, sin fórmulas.';
                        $valor = null;
                    }
                    $fila[$clave] = is_numeric($valor) && !is_string($valor) ? $valor : trim((string) $valor);
                }
                if (collect($fila)->contains(fn ($valor) => $valor !== '') || isset($errores[$numero])) {
                    $filas[$numero] = $fila;
                }
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['excel' => 'Agrega al menos una fila en la hoja Asignaciones.']);
            }

            return [$filas, $errores];
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function validarArchivo(string $archivo): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archivo, ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['excel' => 'Adjunta un archivo XLSX válido.']);
        }
        try {
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $bytes += (int) $zip->statIndex($i)['size'];
                if ($bytes > 24 * 1024 * 1024 || $zip->numFiles > 200) {
                    throw ValidationException::withMessages(['excel' => 'El archivo contiene demasiados datos para la plantilla.']);
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function validar(array $filas, Bodega $bodega, User $usuario, array $errores = []): array
    {
        $nativas = app(Presentacion::class)->nativos('asignaciones');
        $motivos = MotivoAsignacion::where('activo', true)->pluck('codigo')->all();
        $codigos = fn ($clave) => collect($filas)->pluck($clave)->map(fn ($valor) => (string) $valor)->unique()->values()->all();
        $colaboradores = Colaborador::whereKey($codigos('colaborador_codigo'))->get()->keyBy(fn ($fila) => mb_strtolower($fila->codigo));
        $catalogo = Producto::whereKey($codigos('producto_codigo'))->get()->keyBy(fn ($fila) => mb_strtolower($fila->codigo));
        $pares = [];
        $fechas = [];
        foreach ($filas as $numero => &$fila) {
            $fila['colaborador_codigo'] = (string) $fila['colaborador_codigo'];
            $fila['producto_codigo'] = (string) $fila['producto_codigo'];
            foreach (['aprobado_por', 'medio_solicitud', 'motivo_asignacion', 'observaciones'] as $opcional) {
                $fila[$opcional] = blank($fila[$opcional]) ? null : (string) $fila[$opcional];
            }
            $colaborador = $colaboradores->get(mb_strtolower($fila['colaborador_codigo']));
            $producto = $catalogo->get(mb_strtolower($fila['producto_codigo']));
            if ($colaborador?->estado !== 'Activo') {
                $errores[$numero][] = 'Colaborador inexistente o inactivo.';
            }
            if (!$producto) {
                $errores[$numero][] = 'Código de producto inexistente.';
            }
            if (!in_array($fila['cantidad'], [1, 1.0, '1'], true)) {
                $errores[$numero][] = 'La cantidad debe ser 1 por colaborador y código.';
            }
            $fecha = $this->fecha($fila['fecha']);
            if ($fecha === null || $fecha->isFuture()) {
                $errores[$numero][] = 'Usa una fecha válida igual o anterior a hoy, en formato AAAA-MM-DD.';
            } else {
                $fila['fecha'] = $fecha->toDateString();
            }
            if (mb_strlen((string) $fila['aprobado_por']) > 255 || mb_strlen((string) $fila['observaciones']) > ($nativas['observaciones']['max'] ?? 2000)
                || (!(bool) ($nativas['observaciones']['visible'] ?? true) && filled($fila['observaciones']))
                || (($nativas['observaciones']['obligatorio'] ?? false) && blank($fila['observaciones']))) {
                $errores[$numero][] = 'Revisa Aprobado por y Observaciones según el formulario configurado.';
            }
            if (filled($fila['medio_solicitud']) && !in_array($fila['medio_solicitud'], ['Correo', 'WhatsApp'], true)) {
                $errores[$numero][] = 'El medio debe ser Correo o WhatsApp.';
            }
            if (filled($fila['motivo_asignacion']) && !in_array($fila['motivo_asignacion'], $motivos, true)) {
                $errores[$numero][] = 'El código de motivo no está vigente. Consulta la hoja Motivos.';
            }
            if ($colaborador && $producto && $fecha) {
                $fila['colaborador_codigo'] = $colaborador->codigo;
                $fila['producto_codigo'] = $producto->codigo;
                $fila['cantidad'] = (int) $fila['cantidad'];
                $pares[$colaborador->codigo][$producto->codigo][] = $numero;
                $fechas[$colaborador->codigo][$fila['fecha']][] = $numero;
            }
        }
        unset($fila);
        foreach ($pares as $productos) {
            foreach ($productos as $numeros) {
                if (count($numeros) > 1) {
                    foreach ($numeros as $numero) {
                        $errores[$numero][] = 'El colaborador y el código se repiten en el archivo.';
                    }
                }
            }
        }
        foreach ($fechas as $dias) {
            if (count($dias) > 1) {
                foreach (array_merge(...array_values($dias)) as $numero) {
                    $errores[$numero][] = 'Las filas del colaborador deben tener la misma fecha para una sola hoja.';
                }
            }
        }
        $vigentes = AsignacionInventario::custodiaActiva()->with('entrega')->whereIn('colaborador_codigo', array_keys($pares))
            ->whereIn('producto_codigo', collect($filas)->pluck('producto_codigo'))->get()->groupBy('colaborador_codigo');
        [$saldos, $inconsistentes] = $this->saldos($bodega->id, collect($filas)->pluck('producto_codigo')->unique()->all());
        $pedidos = collect($filas)->groupBy('producto_codigo')->map->count();

        foreach ($filas as $numero => $fila) {
            if (isset($errores[$numero])) {
                continue;
            }
            $codigo = $fila['producto_codigo'];
            if (isset($inconsistentes[$codigo])) {
                $errores[$numero][] = 'El saldo de este código requiere conciliación en bodega central.';
            } elseif (($saldos[$codigo] ?? 0) < $pedidos->get($codigo)) {
                $errores[$numero][] = 'El inventario de la bodega central no cubre las filas de este código.';
            }
            $salida = $this->salida($fila['fecha']);
            if ($vigentes->get($fila['colaborador_codigo'], collect())->contains(fn ($previa) =>
                $previa->producto_codigo === $codigo && $salida->lt($previa->entrega?->salida_en ?? $previa->fecha))) {
                $errores[$numero][] = 'La fecha es anterior a la última custodia ordinaria del código.';
            }
        }
        $faltantes = app(ValoresCampos::class)->campos('asignaciones', $usuario, 'formulario')
            ->filter(fn ($campo) => $campo->obligatorio && $campo->predeterminado === null);
        if ($faltantes->isNotEmpty()) {
            $errores[1][] = 'Completa los campos personalizados obligatorios en el formulario manual o configura sus valores predeterminados.';
        }

        return [$filas, $errores];
    }

    private function saldos(int $bodegaId, array $codigos): array
    {
        $tipos = InventarioLifecycleService::CONDICIONES_DISPONIBLES;
        $resumenes = DB::table('inventarios')->where('bodega_id', $bodegaId)->whereIn('stock_tipo', $tipos)
            ->whereIn('producto_codigo', $codigos)->get()->groupBy(['producto_codigo', 'stock_tipo']);
        $individual = (bool) config('inventario.seguimiento_unidades');
        $campo = $individual ? 'condicion' : 'stock_tipo';
        $detalle = DB::table($individual ? 'inventario_existencias' : 'inventario_lotes_operativos')
            ->where('bodega_id', $bodegaId)->whereIn($campo, $tipos)->whereIn('producto_codigo', $codigos)
            ->select('producto_codigo', $campo)->selectRaw(($individual ? 'COUNT(*)' : 'SUM(cantidad)').' AS total')
            ->groupBy('producto_codigo', $campo)->get()->groupBy('producto_codigo');
        $saldos = [];
        $inconsistentes = [];
        foreach ($codigos as $codigo) {
            foreach ($tipos as $tipo) {
                $filas = $resumenes->get($codigo)?->get($tipo) ?? collect();
                $resumen = $filas->first();
                $saldo = (int) ($resumen->cantidad ?? 0);
                $conteo = (int) ($detalle->get($codigo)?->firstWhere($campo, $tipo)?->total ?? 0);
                if ($saldo < 0 || $filas->count() > 1 || $conteo > $saldo
                    || (($individual || ($resumen->lotes_inicializados ?? false)) && $conteo !== $saldo)) {
                    $inconsistentes[$codigo] = true;
                }
                $saldos[$codigo] = ($saldos[$codigo] ?? 0) + $saldo;
            }
        }

        return [$saldos, $inconsistentes];
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if ((is_int($valor) || is_float($valor)) && $valor > 0 && $valor < 2958466) {
            return Carbon::instance(Date::excelToDateTimeObject($valor, config('app.timezone')))->startOfDay();
        }
        if (!is_string($valor) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)
            || !checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return null;
        }

        return Carbon::parse($valor)->startOfDay();
    }

    public function salida(string $dia): Carbon
    {
        return $dia === today()->toDateString() ? now() : Carbon::parse($dia);
    }
}
