<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TraficStatSheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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
        $headers = ["DATE"];

        foreach ($this->operators['commercial'] as $op) {
            $headers[] = $op;
        }
        $headers[] = "AUTRES";
        $headers[] = "TOT/COM";

        foreach ($this->operators['non_commercial'] as $op) {
            $headers[] = $op;
        }
        $headers[] = "AUTRES_NC";
        $headers[] = "T.N/COM";
        $headers[] = "TOT GEN";

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
                ["BUREAU TRAFIC"],
                ["SERVICE VTA"],
                ["RVA AERO/N'DJILI"],
                [$this->title]
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, "");
        }

        $data[] = array_fill(0, $cols, "");
        $data[] = $headings;

        // ✅ DONNÉES : On ne met QUE les dates, le reste sera écrit dans AfterSheet
        foreach ($this->rows as $row) {
            $dataRow = [$row['date'] ?? ''];
            // Remplir le reste avec des chaînes vides (temporaire)
            for ($i = 1; $i < $cols; $i++) {
                $dataRow[] = '';
            }
            $data[] = $dataRow;
        }

        // TOTAUX
        $totRow = ["TOTAUX"];
        for ($i = 2; $i <= $cols; $i++) {
            $totRow[] = '';
        }
        $data[] = $totRow;

        // SIGNATURE
        $data[] = array_fill(0, $cols, "");
        $data[] = array_fill(0, $cols, "");
        $data[] = array_fill(0, $cols, "");

        $signatureLine1 = array_fill(0, $cols, "");
        $signatureLine1[$cols - 3] = "LE CHEF DE BUREAU TRAFIC";
        $data[] = $signatureLine1;

        $signatureLine2 = array_fill(0, $cols, "");
        $signatureLine2[$cols - 3] = "CLAUDE SUMUZEDI N'KILA";
        $data[] = $signatureLine2;

        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // ORIENTATION + FIT TO PAGE
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                // MARGES
                $s->getPageMargins()->setTop(0.4);
                $s->getPageMargins()->setBottom(0.4);
                $s->getPageMargins()->setLeft(0.4);
                $s->getPageMargins()->setRight(0.4);

                // DIMENSIONS
                $highestRow = $s->getHighestRow();
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                // ✅ TITRES : Lignes 1-3 alignées à gauche, ligne 4 centrée
                // Ligne 1 : BUREAU TRAFIC
                $s->mergeCells("A1:{$highestCol}1");
                $s->getStyle("A1")->getFont()->setBold(false)->setSize(12);
                $s->getStyle("A1")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT) // ✅ À gauche
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : SERVICE VTA
                $s->mergeCells("A2:{$highestCol}2");
                $s->getStyle("A2")->getFont()->setBold(false)->setSize(12);
                $s->getStyle("A2")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT) // ✅ À gauche
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 3 : RVA AERO/N'DJILI
                $s->mergeCells("A3:{$highestCol}3");
                $s->getStyle("A3")->getFont()->setBold(false)->setSize(12);
                $s->getStyle("A3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT) // ✅ À gauche
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 4 : TITRE PRINCIPAL (CENTRÉ)
                $s->mergeCells("A4:{$highestCol}4");
                $s->getStyle("A4")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER) // ✅ Centré
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A4")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // LIGNES DE DONNÉES
                $headerRow = 6;
                $firstDataRow = $headerRow + 1;
                $lastDataRow = $highestRow - 5;
                $totalsRow = $lastDataRow;

                // STYLE EN-TÊTES
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
                    ->setBorderStyle(Border::BORDER_MEDIUM);

                // ÉCRIRE LES VALEURS DIRECTEMENT
                $nbCommercialOps = count($this->operators['commercial']);
                $nbNonCommercialOps = count($this->operators['non_commercial']);

                $currentCol = 2;
                $columnMapping = [];

                foreach ($this->operators['commercial'] as $op) {
                    $columnMapping[$currentCol] = ['type' => 'commercial', 'key' => $op];
                    $currentCol++;
                }
                $columnMapping[$currentCol] = ['type' => 'commercial', 'key' => 'AUTRES'];
                $totComCol = ++$currentCol;

                $firstNonCommercialCol = ++$currentCol;
                foreach ($this->operators['non_commercial'] as $op) {
                    $columnMapping[$currentCol] = ['type' => 'non_commercial', 'key' => $op];
                    $currentCol++;
                }
                $columnMapping[$currentCol] = ['type' => 'non_commercial', 'key' => 'AUTRES_NC'];
                $tNComCol = ++$currentCol;
                $totGenCol = ++$currentCol;

                // Écrire les valeurs
                $rowIndex = 0;
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $rowData = $this->rows[$rowIndex] ?? [];

                    foreach ($columnMapping as $colNum => $mapping) {
                        $colLetter = Coordinate::stringFromColumnIndex($colNum);
                        $value = $rowData[$mapping['key']] ?? 0;

                        $s->setCellValueExplicit(
                            "{$colLetter}{$row}",
                            (int)$value,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        );
                    }

                    // Alternance de couleurs
                    if ($rowIndex % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getFill()->setFillType('solid')
                            ->getStartColor()->setARGB('FFF2F2F2');
                    }

                    $rowIndex++;
                }

                // ALIGNEMENT ET FORMAT
                $s->getStyle("A{$firstDataRow}:A{$totalsRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // === FORMULES POUR LIGNES DE DONNÉES ===
                $firstCommercialCol = 2;
                $lastCommercialCol = $totComCol - 1;
                $lastNonCommercialCol = $tNComCol - 1;

                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $startCol = Coordinate::stringFromColumnIndex($firstCommercialCol);
                    $endCol = Coordinate::stringFromColumnIndex($lastCommercialCol);
                    $totComColLetter = Coordinate::stringFromColumnIndex($totComCol);
                    $s->setCellValue("{$totComColLetter}{$row}", "=SUM({$startCol}{$row}:{$endCol}{$row})");

                    $startCol = Coordinate::stringFromColumnIndex($firstNonCommercialCol);
                    $endCol = Coordinate::stringFromColumnIndex($lastNonCommercialCol);
                    $tNComColLetter = Coordinate::stringFromColumnIndex($tNComCol);
                    $s->setCellValue("{$tNComColLetter}{$row}", "=SUM({$startCol}{$row}:{$endCol}{$row})");

                    $totComColLetter = Coordinate::stringFromColumnIndex($totComCol);
                    $totGenColLetter = Coordinate::stringFromColumnIndex($totGenCol);
                    $s->setCellValue("{$totGenColLetter}{$row}", "={$totComColLetter}{$row}+{$tNComColLetter}{$row}");
                }

                // ✅ 1. LIGNE TOTAUX : Les formules calculent sur LA LIGNE elle-même (horizontalement)
                // TOT/COM pour la ligne TOTAUX
                $startCol = Coordinate::stringFromColumnIndex($firstCommercialCol);
                $endCol = Coordinate::stringFromColumnIndex($lastCommercialCol);
                $totComColLetter = Coordinate::stringFromColumnIndex($totComCol);
                $s->setCellValue("{$totComColLetter}{$totalsRow}", "=SUM({$startCol}{$totalsRow}:{$endCol}{$totalsRow})");

                // T.N/COM pour la ligne TOTAUX
                $startCol = Coordinate::stringFromColumnIndex($firstNonCommercialCol);
                $endCol = Coordinate::stringFromColumnIndex($lastNonCommercialCol);
                $tNComColLetter = Coordinate::stringFromColumnIndex($tNComCol);
                $s->setCellValue("{$tNComColLetter}{$totalsRow}", "=SUM({$startCol}{$totalsRow}:{$endCol}{$totalsRow})");

                // TOT GEN pour la ligne TOTAUX
                $totGenColLetter = Coordinate::stringFromColumnIndex($totGenCol);
                $s->setCellValue("{$totGenColLetter}{$totalsRow}", "={$totComColLetter}{$totalsRow}+{$tNComColLetter}{$totalsRow}");

                // Sommes verticales pour toutes les colonnes de données (sauf les colonnes calculées)
                $lastRow = $lastDataRow - 1;
                for ($col = $firstCommercialCol; $col <= $lastCommercialCol; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->setCellValue("{$colLetter}{$totalsRow}", "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastRow})");
                }

                for ($col = $firstNonCommercialCol; $col <= $lastNonCommercialCol; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->setCellValue("{$colLetter}{$totalsRow}", "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastRow})");
                }

                // STYLE LIGNE TOTAUX
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                // $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                //     ->getFill()->setFillType('solid')
                //     ->getStartColor()->setARGB('FFFFC000');
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THICK);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THICK);

                // Hauteur des lignes
                $s->getRowDimension($headerRow)->setRowHeight(25);
                $s->getRowDimension($totalsRow)->setRowHeight(25);

                // ✅ 2 & 3. SIGNATURE : 2 colonnes depuis la droite, fusionnées et centrées
                $signatureRow1 = $totalsRow + 4;
                $signatureRow2 = $signatureRow1 + 1;

                // ✅ 2 colonnes à partir de la droite
                $signatureStartCol = Coordinate::stringFromColumnIndex($highestColIndex - 2);
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                // Ligne 1 : LE CHEF DE BUREAU TRAFIC
                $s->mergeCells("{$signatureStartCol}{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : CLAUDE SUMUZEDI N'KILA
                $s->mergeCells("{$signatureStartCol}{$signatureRow2}:{$signatureEndCol}{$signatureRow2}");
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getFont()->setBold(true)->setSize(12)->setUnderline(true);
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        ];
    }
}
