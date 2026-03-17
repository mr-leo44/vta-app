<?php

namespace App\Exports\Idef;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Feuille annuelle Exonérations.
 *
 * Nationale (3 colonnes) :
 *   A  MOIS  B  CATEGORIE  C  FRET ← brut
 *   Ligne TOTAL : C = SUM(...)     ← formule Excel
 *
 * Internationale (4 colonnes) :
 *   A  MOIS  B  DEBARQUES ← brut  C  EMBARQUES ← brut  D  TOTAL = B+C ← formule Excel
 *   Ligne TOTAL : B,C,D = SUM(...)  ← formules Excel
 */
class ExonerationAnnualStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $rows;
    protected array  $operators;
    protected string $annexeNumber;

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $rows,
        array  $operators,
        string $annexeNumber
    ) {
        $this->rows         = $rows;
        $this->operators    = $operators;
        $this->sheetTitle   = $sheetTitle;
        $this->title        = $title;
        $this->annexeNumber = $annexeNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    // Nationale = 3 cols, Internationale = 4 cols
    private function isNational(): bool
    {
        return $this->sheetTitle === 'FRET EXON NAT';
    }

    public function array(): array
    {
        $cols = $this->isNational() ? 3 : 4;
        $data = [];

        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU IDEF'],
                ["RVA AERO/N'DJILI"],
                ["DIVISION COMMERCIALE"],
                ['', $this->annexeNumber],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        $data[] = array_fill(0, $cols, ''); // ligne espacement

        if ($this->isNational()) {
            $data[] = ['MOIS', "CATEGORIE D'EXONERATION", 'FRET'];
        } else {
            $data[] = ['MOIS', 'DEBARQUES/MONUSCO', 'EMBARQUES/MONUSCO', 'TOTAL'];
        }

        foreach ($this->rows as $monthValues) {
            $monthName = $this->getMonthName($monthValues['MOIS']);
            $copy      = $monthValues;
            array_shift($copy);

            $found = false;
            foreach ($copy as $key => $op) {
                if ($key !== 'UN') continue;
                $found = true;
                if (is_array($op)) {
                    // International : brut arrival + departure, TOTAL = formule
                    $data[] = [
                        $monthName,
                        (int) $op['arrival'],   // B ← brut
                        (int) $op['departure'],  // C ← brut
                        '',                      // D ← formule B+C
                    ];
                } else {
                    // National : brut uniquement
                    $data[] = [$monthName, 'MONUSCO', (int) $op];
                }
            }

            // Aucune donnée pour ce mois → ligne avec 0
            if (!$found) {
                if ($this->isNational()) {
                    $data[] = [$monthName, 'MONUSCO', 0];
                } else {
                    $data[] = [$monthName, 0, 0, ''];
                }
            }
        }

        $data[] = array_pad(['TOTAL'], $cols, '');
        $data[] = array_fill(0, $cols, '');

        foreach (['LE CHEF DE BUREAU IDEF', 'BANZE LUKUNGAY'] as $sig) {
            $sigRow            = array_fill(0, $cols, '');
            $sigRow[$cols - 1] = $sig;
            $data[]            = $sigRow;
        }

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0)
                    ->setHorizontalCentered(true);
                $s->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);

                $highestRow      = $s->getHighestRow();
                $highestCol      = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);
                $isNational      = $this->isNational();

                $headerRow    = 8;
                $firstDataRow = $headerRow + 1;
                $lastDataRow  = $highestRow - 3;

                // ── Formules par ligne de données ─────────────────────────
                if (!$isNational) {
                    // International : D = B + C
                    for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                        $s->getCell("D{$row}")->setValue("=B{$row}+C{$row}");
                        $bVal = $s->getCell("B{$row}")->getValue();
                        $cVal = $s->getCell("C{$row}")->getValue();
                        $s->setCellValueExplicit("B{$row}", ($bVal === '' || $bVal === null) ? 0 : (int) $bVal, DataType::TYPE_NUMERIC);
                        $s->setCellValueExplicit("C{$row}", ($cVal === '' || $cVal === null) ? 0 : (int) $cVal, DataType::TYPE_NUMERIC);
                    }
                } else {
                    // National : forcer numérique sur C (avec 0 si vide)
                    for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                        $cVal = $s->getCell("C{$row}")->getValue();
                        $s->setCellValueExplicit("C{$row}", ($cVal === '' || $cVal === null) ? 0 : (int) $cVal, DataType::TYPE_NUMERIC);
                    }
                }

                // ── Ligne TOTAL ───────────────────────────────────────────
                $lastContent = $lastDataRow - 1;
                if ($isNational) {
                    $s->getCell("C{$lastDataRow}")->setValue("=SUM(C{$firstDataRow}:C{$lastContent})");
                    $s->mergeCells("A{$lastDataRow}:B{$lastDataRow}");
                } else {
                    foreach (['B', 'C', 'D'] as $col) {
                        $s->getCell("{$col}{$lastDataRow}")
                            ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastContent})");
                    }
                }

                // ── Styles ────────────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }
                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);

                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $s->getStyle("A{$headerRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")->getFont()->setSize(14);
                    if ($isNational) {
                        $s->getStyle("B{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                        $s->getStyle("B{$row}")->getFont()->setBold(true);
                        $s->getStyle("{$highestCol}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
                    } else {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
                        $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    }
                }

                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                $s->getRowDimension($headerRow)->setRowHeight(25);
                for ($row = $headerRow; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(24);
                }

                $sigRow1  = $highestRow - 1;
                $sigRow2  = $highestRow;
                $sigStart = Coordinate::stringFromColumnIndex($highestColIndex - 1);
                $sigEnd   = Coordinate::stringFromColumnIndex($highestColIndex);
                $s->mergeCells("{$sigStart}{$sigRow1}:{$sigEnd}{$sigRow1}");
                $s->getStyle("{$sigStart}{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$sigStart}{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->mergeCells("{$sigStart}{$sigRow2}:{$sigEnd}{$sigRow2}");
                $s->getStyle("{$sigStart}{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$sigStart}{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function getMonthName(string $row): string
    {
        $monthNames = [
            '01' => 'JANVIER',
            '02' => 'FÉVRIER',
            '03' => 'MARS',
            '04' => 'AVRIL',
            '05' => 'MAI',
            '06' => 'JUIN',
            '07' => 'JUILLET',
            '08' => 'AOÛT',
            '09' => 'SEPTEMBRE',
            '10' => 'OCTOBRE',
            '11' => 'NOVEMBRE',
            '12' => 'DÉCEMBRE',
        ];
        return $monthNames[explode('-', $row)[0]] ?? $row;
    }
}