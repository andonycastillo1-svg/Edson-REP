<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

abstract class FormattedExcelExport implements FromArray, ShouldAutoSize, WithEvents
{
    protected int $headingRow = 7;

    public function __construct(
        protected string $title,
        protected string $generatedBy,
        protected array $filters = [],
    ) {}

    abstract protected function headings(): array;

    abstract protected function rows(): array;

    protected function totals(): array
    {
        return [];
    }

    protected function numberFormats(): array
    {
        return [];
    }

    public function array(): array
    {
        $rows = $this->rows();
        $filters = collect($this->filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $label) => $label.': '.$value)
            ->implode(' | ');

        return [
            [config('app.name', 'Sistema de Bodegas')],
            [$this->title],
            ['Fecha de generación', now()->format('d/m/Y H:i')],
            ['Usuario', $this->generatedBy],
            ['Filtros aplicados', $filters !== '' ? $filters : 'Ninguno'],
            [],
            $this->headings(),
            ...$rows,
            ...($this->totals() === [] ? [] : [[], ...$this->totals()]),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $columnCount = count($this->headings());
                $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
                $dataCount = count($this->rows());
                $dataEndRow = $this->headingRow + $dataCount;
                $lastRow = count($this->array());

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells("B3:{$lastColumn}3");
                $sheet->mergeCells("B4:{$lastColumn}4");
                $sheet->mergeCells("B5:{$lastColumn}5");

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A3:A5')->getFont()->setBold(true);
                $sheet->getStyle("A{$this->headingRow}:{$lastColumn}{$this->headingRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                if ($dataCount > 0) {
                    $sheet->setAutoFilter("A{$this->headingRow}:{$lastColumn}{$dataEndRow}");
                    $sheet->getStyle("A{$this->headingRow}:{$lastColumn}{$dataEndRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('D1D5DB');
                    $sheet->getStyle('A'.($this->headingRow + 1).":{$lastColumn}{$dataEndRow}")
                        ->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
                }

                foreach ($this->numberFormats() as $column => $format) {
                    if ($dataCount > 0) {
                        $sheet->getStyle("{$column}".($this->headingRow + 1).":{$column}{$dataEndRow}")
                            ->getNumberFormat()->setFormatCode($format);
                    }
                }

                if ($lastRow > $dataEndRow) {
                    $sheet->getStyle('A'.($dataEndRow + 2).":{$lastColumn}{$lastRow}")
                        ->getFont()->setBold(true);
                }

                $sheet->freezePane('A'.($this->headingRow + 1));
                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension($this->headingRow)->setRowHeight(32);
                $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);
                $safeTitle = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $this->title);
                $sheet->setTitle(Str::limit($safeTitle, 31, ''));
            },
        ];
    }
}
