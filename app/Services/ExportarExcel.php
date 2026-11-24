<?php

namespace App\Services;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

class ExportarExcel
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const TIPOS = 'http://schemas.openxmlformats.org/package/2006/content-types';
    private const REL_PAQUETE = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const FILAS_POR_HOJA = 1_048_576;

    /** Las filas se leen una vez y cada hoja usa un archivo temporal para evitar mantener celdas en memoria. */
    public function descargar(string $nombre, array $titulos, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($titulos, $filas) {
            $temporal = $this->temporal();
            $hojas = [];
            try {
                $hojas[] = $ruta = $this->temporal();
                $xml = $this->iniciarHoja($ruta, $titulos);
                $numero = 1;
                foreach ($filas as $fila) {
                    if ($numero === self::FILAS_POR_HOJA) {
                        $this->cerrarHoja($xml, count($titulos), $numero);
                        $hojas[] = $ruta = $this->temporal();
                        $xml = $this->iniciarHoja($ruta, $titulos);
                        $numero = 1;
                    }
                    $this->fila($xml, ++$numero, $fila);
                }
                $this->cerrarHoja($xml, count($titulos), $numero);
                $this->empaquetar($temporal, $hojas);
                readfile($temporal);
            } finally {
                foreach ([$temporal, ...$hojas] as $ruta) {
                    unlink($ruta);
                }
            }
        }, $nombre.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function temporal(): string
    {
        return tempnam(sys_get_temp_dir(), 'edson-xlsx-') ?: throw new RuntimeException('No se pudo iniciar el archivo Excel.');
    }

    private function iniciarHoja(string $ruta, array $titulos): XMLWriter
    {
        $xml = new XMLWriter();
        if (!$xml->openUri($ruta)) {
            throw new RuntimeException('No se pudo iniciar una hoja Excel.');
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', self::NS);
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        $xml->startElement('cols');
        foreach (array_values($titulos) as $i => $titulo) {
            $xml->startElement('col');
            $xml->writeAttribute('min', (string) ($i + 1));
            $xml->writeAttribute('max', (string) ($i + 1));
            $xml->writeAttribute('width', (string) min(44, max(18, mb_strlen($titulo) + 8)));
            $xml->writeAttribute('customWidth', '1');
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData');
        $this->fila($xml, 1, $titulos);

        return $xml;
    }

    private function fila(XMLWriter $xml, int $numero, array $fila): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $numero);
        foreach (array_values($fila) as $i => $valor) {
            if ($valor === null) {
                continue;
            }
            $xml->startElement('c');
            $xml->writeAttribute('r', Coordinate::stringFromColumnIndex($i + 1).$numero);
            if ($valor instanceof DateTimeInterface) {
                $xml->writeAttribute('s', '2');
                $xml->writeElement('v', (string) Date::PHPToExcel($valor));
            } elseif (is_float($valor) || (is_int($valor) && abs($valor) < 1e15)) {
                if (is_float($valor)) {
                    $xml->writeAttribute('s', '3');
                }
                $xml->writeElement('v', (string) $valor);
            } else {
                if ($numero === 1) {
                    $xml->writeAttribute('s', '1');
                }
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $texto = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $valor);
                $texto = preg_replace('/_(?=x[0-9a-f]{4}_)/i', '_x005F_', mb_substr($texto ?? '', 0, 32767));
                $xml->text($texto ?? '');
                $xml->endElement();
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->flush();
    }

    private function cerrarHoja(XMLWriter $xml, int $columnas, int $filas): void
    {
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', 'A1:'.Coordinate::stringFromColumnIndex($columnas).$filas);
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
    }

    private function empaquetar(string $ruta, array $hojas): void
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo preparar el archivo Excel.');
        }
        $tipos = '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $relaciones = '<Relationship Id="estilos" Type="'.self::REL.'/styles" Target="styles.xml"/>';
        $libro = '';
        foreach ($hojas as $i => $hoja) {
            $numero = $i + 1;
            $nombre = "worksheets/sheet$numero.xml";
            if (!$zip->addFile($hoja, 'xl/'.$nombre)) {
                throw new RuntimeException('No se pudo incluir una hoja en el archivo Excel.');
            }
            $tipos .= '<Override PartName="/xl/'.$nombre.'" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $relaciones .= '<Relationship Id="rId'.$numero.'" Type="'.self::REL.'/worksheet" Target="'.$nombre.'"/>';
            $libro .= '<sheet name="Datos '.$numero.'" sheetId="'.$numero.'" r:id="rId'.$numero.'"/>';
        }
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="'.self::TIPOS.'">'.$tipos.'</Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="'.self::REL_PAQUETE.'"><Relationship Id="libro" Type="'.self::REL.'/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="'.self::REL_PAQUETE.'">'.$relaciones.'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="'.self::NS.'" xmlns:r="'.self::REL.'"><bookViews><workbookView/></bookViews><sheets>'.$libro.'</sheets></workbook>');
        $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="'.self::NS.'">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/>'
            .'<xf xfId="0" numFmtId="164" applyNumberFormat="1"/><xf xfId="0" numFmtId="4" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        if (!$zip->close()) {
            throw new RuntimeException('No se pudo guardar el archivo Excel.');
        }
    }
}
