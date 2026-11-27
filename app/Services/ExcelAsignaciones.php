<?php

namespace App\Services;

use App\Models\AsignacionInventario;
use App\Models\Bodega;
use App\Models\Colaborador;
use App\Models\Consolidado;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
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

    private const SUSTITUCION = 'Sustituir asignación (ID)';

    private const REFERENCIAS = ['Colaborador', 'Producto', 'Pendiente al descargar', 'Consolidado', 'Verificación'];

    public function plantilla(?Consolidado $consolidado = null, ?Collection $pendientes = null, bool $porCorte = false): Xlsx
    {
        $vinculado = $consolidado || $porCorte;
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Asignaciones');
        $encabezados = [...array_values(self::COLUMNAS), self::SUSTITUCION];
        if ($vinculado) {
            array_push($encabezados, ...self::REFERENCIAS);
        }
        if ($porCorte) {
            $encabezados[] = 'Asignadas antes del tramo';
            $hoja->getColumnDimension('O')->setVisible(false);
        }
        $hoja->fromArray([$encabezados]);
        $hoja->freezePane('A2');
        foreach (range('A', $vinculado ? 'N' : 'I') as $columna) {
            $hoja->getColumnDimension($columna)->setWidth(26);
        }
        $hoja->getStyle($vinculado ? 'A1:N1' : 'A1:I1')->getFont()->setBold(true);
        $hoja->getStyle('A2:B501')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $libro->createSheet()->setTitle('Ejemplo')->fromArray([
            array_values(self::COLUMNAS),
            ['COL-001', 'P-001', 1, today()->toDateString(), 'Gerencia', 'Correo', '', 'Ejemplo ficticio'],
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
        $medios = $libro->createSheet()->setTitle('Medios');
        $medios->fromArray([['Medio de solicitud'], ['Correo'], ['WhatsApp'], [RegistrarAsignaciones::MEDIO_INTERNO]]);
        $libro->addNamedRange(new NamedRange('MediosSolicitud', $medios, '$A$2:$A$4'));
        $hoja->getCell('F2')->getDataValidation()->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)
            ->setShowDropDown(true)->setShowErrorMessage(true)->setError('Escoge un medio de solicitud disponible.')
            ->setFormula1('MediosSolicitud')->setSqref('F2:F501');
        if ($vinculado) {
            $origen = $libro->createSheet()->setTitle('Origen');
            $origen->setCellValueExplicit('A1', Crypt::encryptString($porCorte ? 'edson-asignaciones-corte' : 'edson-asignaciones:'.$consolidado->id), DataType::TYPE_STRING);
            $origen->setSheetState('hidden');
            $hoja->getColumnDimension('N')->setVisible(false);
            foreach ($pendientes->values() as $i => $detalle) {
                $fila = $i + 2;
                $persona = $detalle->participante;
                $destino = $porCorte ? $persona->consolidado : $consolidado;
                $pendiente = $detalle->cantidad - $detalle->asignaciones_count;
                $hoja->fromArray([[($detalle->cantidad_en_archivo ?? min(RegistrarAsignaciones::MAX_UNIDADES, $pendiente)), today()->toDateString(),
                    null, RegistrarAsignaciones::MEDIO_INTERNO]], startCell: 'C'.$fila);
                $inicio = $porCorte ? (int) $detalle->asignadas_antes_de_tramo : null;
                if ($inicio !== null) {
                    $hoja->setCellValue('O'.$fila, $inicio);
                }
                foreach ([
                    'A' => $persona->colaborador_codigo, 'B' => $detalle->producto_codigo,
                    'J' => ($persona->colaborador?->nombre ?? $persona->colaborador_codigo).' — '.$persona->colaborador_codigo, 'K' => $detalle->producto?->nombre ?? '',
                    'M' => '#'.$destino->id.' · '.$destino->nombre,
                    'N' => $this->firmaFila($destino->id, $persona->colaborador_codigo, $detalle->producto_codigo, $inicio),
                ] as $columna => $valor) {
                    $hoja->setCellValueExplicit($columna.$fila, $valor, DataType::TYPE_STRING);
                }
                $hoja->setCellValue('L'.$fila, $pendiente);
            }
        }
        $libro->setActiveSheetIndex(0);

        return new Xlsx($libro);
    }

    public function leer(string $archivo): array
    {
        $this->validarArchivo($archivo);
        $lector = new LectorXlsx();
        $lector->setReadDataOnly(true);
        $lector->setLoadSheetsOnly(['Asignaciones', 'Origen']);
        $lector->setReadFilter(new class implements IReadFilter {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= 502 && in_array($columnAddress, range('A', 'O'), true);
            }
        });
        try {
            $info = collect($lector->listWorksheetInfo($archivo))->firstWhere('worksheetName', 'Asignaciones');
            if ($info === null || $info['totalRows'] > self::MAX_FILAS + 1 || $info['totalColumns'] > 15) {
                throw new \RuntimeException('Dimensiones de hoja fuera del formato.');
            }
            $libro = $lector->load($archivo);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['excel' => 'Usa la hoja Asignaciones de la plantilla con hasta 500 filas.']);
        }

        try {
            $hoja = $libro->getSheetByName('Asignaciones');
            $origen = $libro->getSheetByName('Origen');
            $id = null;
            $porCorte = false;
            if ($origen) {
                try {
                    $clave = Crypt::decryptString((string) $origen->getCell('A1')->getValue());
                } catch (DecryptException) {
                    $clave = '';
                }
                $porCorte = $clave === 'edson-asignaciones-corte';
                if (!$porCorte && !preg_match('/^edson-asignaciones:([1-9][0-9]*)$/D', $clave, $partes)) {
                    throw ValidationException::withMessages(['excel' => 'Descarga nuevamente el archivo de pendientes para recuperar su origen.']);
                }
                $id = $porCorte ? null : (int) $partes[1];
            }
            $columnas = self::COLUMNAS;
            if ($info['totalColumns'] > 8) {
                $columnas['sustituir_asignacion_id'] = self::SUSTITUCION;
            }
            $encabezados = [...array_values($columnas), ...($origen ? self::REFERENCIAS : [])];
            if ($porCorte) {
                $encabezados[] = 'Asignadas antes del tramo';
            }
            if (!$hoja || $info['totalColumns'] !== count($encabezados)
                || $hoja->rangeToArray('A1:'.chr(64 + count($encabezados)).'1', null, false, false)[0] !== $encabezados) {
                throw ValidationException::withMessages(['excel' => 'Conserva los encabezados y la hoja de origen de la plantilla.']);
            }
            $filas = [];
            $errores = [];
            for ($numero = 2; $numero <= $hoja->getHighestDataRow(); $numero++) {
                $fila = [];
                foreach (array_keys($columnas) as $i => $clave) {
                    $celda = $hoja->getCell(chr(65 + $i).$numero);
                    $valor = $celda->getValue();
                    if ($celda->getDataType() === DataType::TYPE_FORMULA || (is_object($valor) && !$valor instanceof \Stringable)) {
                        $errores[$numero][] = 'Usa valores simples, sin fórmulas.';
                        $valor = null;
                    }
                    $fila[$clave] = is_numeric($valor) && !is_string($valor) ? $valor : trim((string) $valor);
                }
                if (collect($fila)->contains(fn ($valor) => $valor !== '') || isset($errores[$numero])) {
                    $idFila = $id;
                    $inicio = null;
                    if ($porCorte) {
                        $idFila = preg_match('/^#([1-9][0-9]*) · /u', (string) $hoja->getCell('M'.$numero)->getValue(), $origenFila)
                            ? (int) $origenFila[1] : null;
                        $celdaInicio = $hoja->getCell('O'.$numero);
                        $inicio = filter_var($celdaInicio->getValue(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                        if ($inicio === false || $celdaInicio->getDataType() === DataType::TYPE_FORMULA) {
                            $errores[$numero][] = 'El tramo del archivo no es válido. Descarga nuevamente los pendientes.';
                        } else {
                            $fila['asignadas_antes_de_tramo'] = $inicio;
                        }
                        $fila['consolidado_id'] = $idFila;
                    }
                    if ($origen && (!$idFila || $inicio === false || !hash_equals($this->firmaFila($idFila,
                        (string) $fila['colaborador_codigo'], (string) $fila['producto_codigo'], $inicio),
                        (string) $hoja->getCell('N'.$numero)->getValue()))) {
                        $errores[$numero][] = 'Conserva los códigos de la fila descargada. Puedes cambiar la cantidad o eliminar la fila.';
                    }
                    $filas[$numero] = $fila;
                }
            }
            if ($filas === []) {
                throw ValidationException::withMessages(['excel' => 'Agrega al menos una fila en la hoja Asignaciones.']);
            }

            return [$filas, $errores, $id, $porCorte];
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function firmaFila(int $id, string $colaborador, string $producto, ?int $inicio = null): string
    {
        $datos = [$id, $colaborador, $producto];
        if ($inicio !== null) {
            $datos[] = $inicio;
        }

        return hash_hmac('sha256', json_encode($datos, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function huellaOperativa(int $bodega, array $filas): string
    {
        $salidas = collect($filas)->sortBy(['colaborador_codigo', 'producto_codigo', 'cantidad', 'fecha',
            'consolidado_id', 'asignadas_antes_de_tramo'])->map(fn ($fila) => [
            (string) $fila['colaborador_codigo'], (string) $fila['producto_codigo'], (int) $fila['cantidad'], $fila['fecha'],
            ...isset($fila['asignadas_antes_de_tramo']) ? [(int) $fila['consolidado_id'], $fila['asignadas_antes_de_tramo']] : [],
        ])->values();

        return hash('sha256', $bodega.'|'.$salidas->toJson());
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

    public function validar(array $filas, Bodega $bodega, User $usuario, array $errores = [], ?Consolidado $consolidado = null, ?Collection $porCorte = null): array
    {
        $nativas = app(Presentacion::class)->nativos('asignaciones');
        $motivos = MotivoAsignacion::where('activo', true)->pluck('codigo')->all();
        $codigos = fn ($clave) => collect($filas)->pluck($clave)->map(fn ($valor) => (string) $valor)->unique()->values()->all();
        $colaboradores = Colaborador::whereKey($codigos('colaborador_codigo'))->get()->keyBy(fn ($fila) => mb_strtolower($fila->codigo));
        $catalogo = Producto::whereKey($codigos('producto_codigo'))->get()->keyBy(fn ($fila) => mb_strtolower($fila->codigo));
        $pares = [];
        $fechas = [];
        $codigosFilas = [];
        foreach ($filas as $numero => &$fila) {
            $origen = $porCorte?->get($fila['consolidado_id'] ?? 0) ?? $consolidado;
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
            $cantidad = filter_var($fila['cantidad'], FILTER_VALIDATE_INT);
            if ($cantidad < 1 || $cantidad > ($origen ? RegistrarAsignaciones::MAX_UNIDADES : 1)) {
                $errores[$numero][] = $origen
                    ? 'La cantidad debe ser un entero de 1 a '.RegistrarAsignaciones::MAX_UNIDADES.'.'
                    : 'La cantidad debe ser 1 por colaborador y código.';
            }
            if (filled($fila['sustituir_asignacion_id'] ?? null)) {
                $anterior = filter_var($fila['sustituir_asignacion_id'], FILTER_VALIDATE_INT);
                if ($anterior < 1 || $cantidad !== 1) {
                    $errores[$numero][] = 'Para un reemplazo indica el ID de la asignación vigente y una unidad.';
                }
                $fila['sustituir_asignacion_id'] = $anterior;
            } elseif (filled($fila['motivo_asignacion'])) {
                $errores[$numero][] = 'Selecciona la asignación vigente que sustituirás para usar este motivo.';
            } else {
                unset($fila['sustituir_asignacion_id']);
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
            if (filled($fila['medio_solicitud']) && !in_array($fila['medio_solicitud'], ['Correo', 'WhatsApp', RegistrarAsignaciones::MEDIO_INTERNO], true)) {
                $errores[$numero][] = 'Selecciona un medio de solicitud válido.';
            }
            if ($origen) {
                $fila['medio_solicitud'] = RegistrarAsignaciones::MEDIO_INTERNO;
            } elseif ($fila['medio_solicitud'] === RegistrarAsignaciones::MEDIO_INTERNO) {
                $errores[$numero][] = 'El medio Requerimiento interno requiere un consolidado.';
            }
            if (filled($fila['motivo_asignacion']) && !in_array($fila['motivo_asignacion'], $motivos, true)) {
                $errores[$numero][] = 'El código de motivo no está vigente. Consulta la hoja Motivos.';
            }
            if ($colaborador && $producto && $fecha) {
                $fila['colaborador_codigo'] = $colaborador->codigo;
                $fila['producto_codigo'] = $producto->codigo;
                $fila['cantidad'] = (int) $fila['cantidad'];
                $grupo = ($origen?->id ?? 0).'|'.$colaborador->codigo;
                $pares[$grupo][$producto->codigo][] = $numero;
                $fechas[$grupo][$fila['fecha']][] = $numero;
                $codigosFilas[] = $colaborador->codigo;
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
        $vigentes = AsignacionInventario::custodiaActiva()->with('entrega')->whereIn('colaborador_codigo', array_unique($codigosFilas))
            ->whereIn('producto_codigo', collect($filas)->pluck('producto_codigo'))->get()->groupBy('colaborador_codigo');
        [$saldos, $inconsistentes] = $this->saldos($bodega->id, collect($filas)->pluck('producto_codigo')->unique()->all());
        $pedidos = collect($filas)->groupBy('producto_codigo')->map(fn ($grupo) => $grupo->sum('cantidad'));
        if ($pedidos->sum() > RegistrarAsignaciones::MAX_UNIDADES) {
            $errores[1][] = 'Divide la operación en archivos de hasta '.RegistrarAsignaciones::MAX_UNIDADES.' unidades.';
        }
        $origenes = $porCorte ?? ($consolidado ? collect([$consolidado->id => $consolidado]) : collect());
        $pendientes = $origenes->map(fn ($origen) => app(RequerimientosAsignacion::class)
            ->pendientes($origen, usuario: $usuario, permiso: 'flujo.importar')
            ->groupBy(fn ($fila) => $fila->participante->colaborador_codigo));

        foreach ($filas as $numero => $fila) {
            if (isset($errores[$numero])) {
                continue;
            }
            $codigo = $fila['producto_codigo'];
            if ($idOrigen = ($fila['consolidado_id'] ?? $consolidado?->id)) {
                $necesidad = $pendientes->get($idOrigen)?->get($fila['colaborador_codigo'], collect())->firstWhere('producto_codigo', $codigo);
                if (!$necesidad || $fila['cantidad'] > $necesidad->cantidad - $necesidad->asignaciones_count) {
                    $errores[$numero][] = 'La cantidad supera el pendiente aprobado de este producto para el colaborador.';
                } elseif (isset($fila['asignadas_antes_de_tramo']) && $fila['asignadas_antes_de_tramo'] !== $necesidad->asignaciones_count) {
                    $errores[$numero][] = 'Cambió el avance de la entrega. Descarga los pendientes actualizados o confirma primero la parte anterior.';
                }
            }
            if (isset($inconsistentes[$codigo])) {
                $errores[$numero][] = 'El saldo de este código requiere conciliación en bodega central.';
            } elseif (($saldos[$codigo] ?? 0) < $pedidos->get($codigo)) {
                $errores[$numero][] = 'El inventario de la bodega central no cubre las filas de este código.';
            }
            $salida = $this->salida($fila['fecha']);
            $custodias = $vigentes->get($fila['colaborador_codigo'], collect());
            if (isset($fila['sustituir_asignacion_id'])) {
                $anterior = $custodias->firstWhere('id', $fila['sustituir_asignacion_id']);
                if (!$anterior || $anterior->producto_codigo !== $codigo || !app(AccesoCustodia::class)->puedeVer($usuario, $anterior)) {
                    $errores[$numero][] = 'El ID debe corresponder a una asignación vigente y autorizada del mismo colaborador y producto.';
                } elseif (blank($fila['motivo_asignacion']) && app(AsignacionVidaUtilService::class)->restante($anterior, $salida, 1) !== 0) {
                    $errores[$numero][] = 'Indica el motivo del reemplazo si la vida útil está vigente o es desconocida.';
                }
            }
            if ($custodias->contains(fn ($previa) =>
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
