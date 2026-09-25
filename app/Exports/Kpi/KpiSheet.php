<?php

namespace App\Exports\Kpi;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KpiSheet implements FromArray, WithColumnWidths, WithDrawings, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $rows,
        private readonly array $images = [],
        private readonly string $orientation = PageSetup::ORIENTATION_PORTRAIT,
    ) {}

    public function array(): array
    {
        return $this->rows ?: [['Data belum tersedia untuk periode ini.']];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 28, 'C' => 28, 'D' => 28, 'E' => 28, 'F' => 28];
    }

    public function drawings(): array
    {
        return collect($this->images)->map(function (array $image): Drawing {
            $drawing = new Drawing();
            $drawing->setName($image['name'] ?? 'Signature');
            $drawing->setDescription($image['name'] ?? 'Signature');
            $drawing->setPath($image['path']);
            $drawing->setHeight(64);
            $drawing->setCoordinates($image['coordinate'] ?? 'C1');
            return $drawing;
        })->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(1, count($this->rows));
        $lastColumn = 'F';
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation($this->orientation)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical('top')->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(26);
        foreach ($this->rows as $index => $row) {
            if (($row[0] ?? '') !== '') {
                $sheet->getStyle('A'.($index + 1))->getFont()->setBold(true);
            }
            if (in_array(mb_strtoupper((string) ($row[0] ?? '')), ['NILAI AKHIR KPI', 'MONTHLY PERFORMANCE APPRAISAL', 'KINERJA INDIVIDU', 'KINERJA OPERASIONAL', 'DAILY REPORT', 'PERSETUJUAN', 'TANDA TANGAN NILAI AKHIR'], true)) {
                $sheet->mergeCells('A'.($index + 1).':F'.($index + 1));
                $sheet->getStyle('A'.($index + 1))->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('173467');
            }
        }

        return [
            "A1:{$lastColumn}{$lastRow}" => [
                'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '334155']],
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => 'DCE3ED']]],
            ],
            'A1:F1' => ['fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DBEAFE']]],
        ];
    }
}
