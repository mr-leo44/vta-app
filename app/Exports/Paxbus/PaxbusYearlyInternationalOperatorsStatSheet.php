<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PaxbusYearlyInternationalOperatorsStatSheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $subTitle;
    protected $rows;
    protected $operators;

    public function __construct(string $sheetTitle, string $title, ?string $subTitle, array $rows, $operators)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->subTitle = $subTitle;
        $this->rows = $rows;
        $this->operators = $operators;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    protected function headings(): array
    {
        $headers = ['MOIS'];

        foreach ($this->operators as $op) {
            $headers[] = $op;
        }

        $headers[] = 'TOTAL GEN.';

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
                ["SERVICE VTA"],
                ["BUREAU PAX BUS"],
                ["RVA AERO/N'DJILI"],
                [""],
                [$this->title]

            ] as $line
        ) {
            $data[] = array_pad($line, $cols, "");
        }

        if ($this->subTitle) $data[] = array_pad([$this->subTitle], $cols, "");

        $data[] = $headings;


        foreach ($this->rows['pax'] as $row) {
            $month = $this->getMonthName($row['date']);
            $dataRow = [$month ?? ''];


            // Ajouter les valeurs pour chaque opérateur
            foreach ($this->operators as $op) {
                $dataRow[] = $row[$op] ?? 0;
            }

            // Colonne TOTAL (sera calculée par formule Excel)
            $dataRow[] = '';

            $data[] = $dataRow;
        }

        $totRow = ["TOTAUX"];
        for ($i = 1; $i < $cols; $i++) {
            $totRow[] = '';
        }
        $data[] = $totRow;

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
        $signatureLine1 = array_fill(0, $cols, '');
        $signatureLine1[$cols - 5] = 'LE CHEF DE BUREAU PAX BUS ai';
        $data[] = $signatureLine1;

        $signatureLine2 = array_fill(0, $cols, '');
        $signatureLine2[$cols - 5] = 'FREDDY KALEMA TABU';
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
                    ->setFitToHeight(1)
                    ->setHorizontalCentered(true);

                // MARGES
                $s->getPageMargins()->setTop(0.5);
                $s->getPageMargins()->setBottom(0.5);
                $s->getPageMargins()->setLeft(0.5);
                $s->getPageMargins()->setRight(0.5);

                // ✅ CALCUL DES INDICES CORRECTS
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);
                
                $headerRow = 7;
                $firstDataRow = $headerRow + 1;
                $lastDataRow = 7 + count($this->rows['pax']);
                $totalsRow = $lastDataRow + 1;

                // Indices des colonnes
                $totalColIndex = count($this->operators) + 2; // DATE + operators + TOTAL
                $lastDataColIndex = $totalColIndex - 1;

                // ═══════════════════════════════════════════════════════════
                // STYLE DES TITRES (Lignes 1-5)
                // ═══════════════════════════════════════════════════════════
                
                // Lignes 1-3 : Alignées à gauche
                for ($row = 1; $row <= 3; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(10);
                    $s->getStyle("A{$row}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                }
                // Ligne 5 : TITRE PRINCIPAL (CENTRÉ)
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(12);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle('A6')->getFont()->setBold(true)->setSize(12);
                $s->getStyle('A6')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // ═══════════════════════════════════════════════════════════
                // STYLE EN-TÊTES (Ligne 6)
                // ═══════════════════════════════════════════════════════════
                
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

                // ═══════════════════════════════════════════════════════════
                // BORDURES DU TABLEAU
                // ═══════════════════════════════════════════════════════════
                
                $s->getStyle("A{$headerRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // ═══════════════════════════════════════════════════════════
                // ALTERNANCE DE COULEURS POUR LES LIGNES DE DONNÉES
                // ═══════════════════════════════════════════════════════════
                
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $rowIndex = $row - $firstDataRow;
                    if ($rowIndex % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getFill()->setFillType('solid')
                            ->getStartColor()->setARGB('FFF2F2F2');
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // ALIGNEMENT ET FORMAT DES NOMBRES
                // ═══════════════════════════════════════════════════════════
                
                // Colonne DATE : alignée à gauche
                $s->getStyle("A{$firstDataRow}:A{$totalsRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Parcourir toutes les lignes de données pour définir explicitement les valeurs
                $rowIndex = 0;
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $rowData = $this->rows['pax'][$rowIndex] ?? [];
                    
                    // Pour chaque opérateur
                    $colIndex = 2; // Commence à la colonne B (après DATE)
                    foreach ($this->operators as $op) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $value = $rowData[$op] ?? 0;
                        
                        // Utiliser setCellValueExplicit pour forcer l'affichage des 0
                        $s->setCellValueExplicit(
                            "{$colLetter}{$row}",
                            (int)$value,
                            DataType::TYPE_NUMERIC
                        );
                        
                        $colIndex++;
                    }
                    
                    $rowIndex++;
                }

                // Colonnes numériques : alignées à droite avec format nombre
                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$totalsRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0');
                }

                // ═══════════════════════════════════════════════════════════
                // ✅ FORMULES EXCEL POUR LES TOTAUX
                // ═══════════════════════════════════════════════════════════
                
                // Colonne TOTAL pour chaque ligne de données (somme horizontale)
                $totalColLetter = Coordinate::stringFromColumnIndex($totalColIndex);
                $firstDataColLetter = Coordinate::stringFromColumnIndex(2);
                $lastDataColLetter = Coordinate::stringFromColumnIndex($lastDataColIndex);
                
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->setCellValue(
                        "{$totalColLetter}{$row}", 
                        "=SUM({$firstDataColLetter}{$row}:{$lastDataColLetter}{$row})"
                    );
                }

                // Ligne TOTAUX : somme verticale pour chaque colonne
                for ($col = 2; $col <= $totalColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->setCellValue(
                        "{$colLetter}{$totalsRow}", 
                        "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow})"
                    );
                }

                // ═══════════════════════════════════════════════════════════
                // STYLE LIGNE TOTAUX
                // ═══════════════════════════════════════════════════════════
                
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);

                // ═══════════════════════════════════════════════════════════
                // HAUTEUR DES LIGNES
                // ═══════════════════════════════════════════════════════════
                
                $s->getRowDimension($headerRow)->setRowHeight(20);
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ═══════════════════════════════════════════════════════════
                // ✅ SIGNATURE (2 colonnes depuis la droite, fusionnées)
                // ═══════════════════════════════════════════════════════════
                
                $signatureRow1 = $totalsRow + 2;
                $signatureRow2 = $signatureRow1 + 1;

                // 2 colonnes à partir de la droite
                $signatureStartCol = Coordinate::stringFromColumnIndex($highestColIndex - 4);
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("{$signatureStartCol}{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("{$signatureStartCol}{$signatureRow2}:{$signatureEndCol}{$signatureRow2}");
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function getMonthName($month): string
    {
        $explodedMonth = explode('-', $month)[0];
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
        return $monthNames[$explodedMonth] ?? '';
    }
}
