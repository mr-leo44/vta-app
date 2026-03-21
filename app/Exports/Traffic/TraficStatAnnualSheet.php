<?php

namespace App\Exports\Traffic;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class TraficStatAnnualSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected $sheetTitle;

    protected $title;

    protected $rows;

    protected $operators;

    public function __construct(string $sheetTitle, string $title, array $rows, array $operators)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->rows = $rows;
        $this->operators = $operators;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    protected function headings(): array
    {
        $headers = ['MOIS'];

        foreach ($this->operators['commercial'] as $op) {
            $headers[] = $op;
        }

        $headers[] = 'AUTRES';
        $headers[] = 'TOT/COM';

        foreach ($this->operators['non_commercial'] as $op) {
            $headers[] = $op;
        }

        $headers[] = 'AUTRES_NC';
        $headers[] = 'T.N/COM';
        $headers[] = 'TOT GEN';

        return $headers;
    }

    public function array(): array
    {
        $headings = $this->headings();
        $cols = count($headings);
        $data = [];

        // TITRES
        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU TRAFIC'],
                ["RVA AERO/N'DJILI"],
                [''],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        $data[] = $headings;

        // === 12 LIGNES MOIS ===
        $monthNames = [
            'JANVIER', 'FÉVRIER', 'MARS', 'AVRIL',
            'MAI', 'JUIN', 'JUILLET', 'AOÛT',
            'SEPTEMBRE', 'OCTOBRE', 'NOVEMBRE', 'DÉCEMBRE',
        ];

        for ($i = 0; $i < 12; $i++) {
            $row = [$monthNames[$i]];

            for ($c = 1; $c < $cols; $c++) {
                $row[] = '';
            }

            $data[] = $row;
        }

        // TOTAUX
        $totRow = ['TOTAL'];
        for ($i = 1; $i < $cols; $i++) {
            $totRow[] = '';
        }
        $data[] = $totRow;

        // SIGNATURE
        $data[] = array_fill(0, $cols, '');

        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 3] = 'LE CHEF DE BUREAU TRAFIC';
        $data[] = $sig1;

        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 3] = "CLAUDE SUMUZEDI N'KILA";
        $data[] = $sig2;

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $s = $event->sheet->getDelegate();

                // === CONFIG PAGE ===
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.25);
                $s->getPageMargins()->setRight(0.25);

                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                // === TITRES ===
                foreach ([1, 2, 3] as $i) {
                    $s->mergeCells("A{$i}:{$highestCol}{$i}");
                    $s->getStyle("A{$i}")->getFont()->setBold(false)->setSize(10);
                    $s->getStyle("A{$i}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(16);
                $s->getStyle('A5')
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // === TABLEAU ===
                $headerRow = 6;
                $firstDataRow = 7;
                $lastDataRow = $firstDataRow + 11; // 12 mois
                $totalsRow = $lastDataRow + 1;

                // HEADERS
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // BORDURES
                $s->getStyle("A{$headerRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $col = 2;
                $map = [];

                foreach ($this->operators['commercial'] as $op) {
                    $map[$col] = ['type' => 'commercial', 'key' => $op];
                    $col++;
                }

                $map[$col] = ['type' => 'commercial', 'key' => 'AUTRES'];

                $totComCol = ++$col;

                $firstNCCol = ++$col;

                foreach ($this->operators['non_commercial'] as $op) {
                    $map[$col] = ['type' => 'non_commercial', 'key' => $op];
                    $col++;
                }

                $map[$col] = ['type' => 'non_commercial', 'key' => 'AUTRES_NC'];

                $tNComCol = ++$col;
                $totGenCol = ++$col;

                // === REMPLISSAGE PAR MOIS ===
                for ($i = 0; $i < 12; $i++) {

                    $excelRow = $firstDataRow + $i;
                    $rowData = $this->rows[$i] ?? [];

                    foreach ($map as $colNum => $mapping) {
                        $letter = Coordinate::stringFromColumnIndex($colNum);
                        $value = $rowData[$mapping['key']] ?? 0;

                        $s->setCellValue("{$letter}{$excelRow}", (int) $value);
                    }
                }

                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // === FORMULES ===
                // TOT/COM
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $start = Coordinate::stringFromColumnIndex(2);
                    $end = Coordinate::stringFromColumnIndex($totComCol - 1);
                    $tot = Coordinate::stringFromColumnIndex($totComCol);
                    $s->setCellValue("{$tot}{$r}", "=SUM({$start}{$r}:{$end}{$r})");
                }

                // T.N/COM
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $start = Coordinate::stringFromColumnIndex($firstNCCol);
                    $end = Coordinate::stringFromColumnIndex($tNComCol - 1);
                    $tot = Coordinate::stringFromColumnIndex($tNComCol);
                    $s->setCellValue("{$tot}{$r}", "=SUM({$start}{$r}:{$end}{$r})");
                }

                // TOT GEN
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $totComL = Coordinate::stringFromColumnIndex($totComCol);
                    $totNComL = Coordinate::stringFromColumnIndex($tNComCol);
                    $totGenL = Coordinate::stringFromColumnIndex($totGenCol);

                    $s->setCellValue("{$totGenL}{$r}", "={$totComL}{$r}+{$totNComL}{$r}");
                }

                // === TOTAUX VERTICAUX ===
                for ($c = 2; $c <= $totGenCol; $c++) {
                    $letter = Coordinate::stringFromColumnIndex($c);
                    $s->setCellValue("{$letter}{$totalsRow}",
                        "=SUM({$letter}{$firstDataRow}:{$letter}{$lastDataRow})"
                    );
                }

                // === STYLE TOTALS ===
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // === SIGNATURE ===
                $sig1 = $totalsRow + 2;
                $sig2 = $sig1 + 1;

                $startSig = Coordinate::stringFromColumnIndex($highestColIndex - 2);
                $endSig = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("{$startSig}{$sig1}:{$endSig}{$sig1}");
                $s->mergeCells("{$startSig}{$sig2}:{$endSig}{$sig2}");

                $s->getStyle("{$startSig}{$sig1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $s->getStyle("{$startSig}{$sig2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
