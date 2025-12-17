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

class PaxbusInternationalStatSheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $rows;
    protected $operators;

    public function __construct(string $sheetTitle, string $title, array $rows, $operators)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title      = $title;
        $this->rows       = $rows;
        $this->operators  = $operators;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    protected function headings(): array
    {
        $headers = ["DATE"];

        foreach ($this->operators as $op) {
            $headers[] = $op;
        }

        $headers[] = "TOTAL";

        return $headers;
    }

    public function array(): array
    {
        $headings = $this->headings();
        $cols = count($headings);
        $data = [];

        // TITRES
        foreach ([
            ["SERVICE VTA"],
            ["BUREAU PAX BUS"],
            ["RVA AERO/N'DJILI"],
            [""],
            [$this->title]
        ] as $line) {
            $data[] = array_pad($line, $cols, "");
        }

        // ✅ LIGNE D'EN-TÊTES
        $data[] = $headings;

        // ✅ LIGNES DE DONNÉES avec valeurs réelles
        foreach ($this->rows['pax'] as $row) {
            $dataRow = [$row['date'] ?? ''];
            
            // Ajouter les valeurs pour chaque opérateur
            foreach ($this->operators as $op) {
                $dataRow[] = $row[$op] ?? 0;
            }
            
            // Colonne TOTAL (sera calculée par formule Excel)
            $dataRow[] = '';
            
            $data[] = $dataRow;
        }

        // LIGNE TOTAUX
        $totRow = ["TOTAUX"];
        for ($i = 1; $i < $cols; $i++) {
            $totRow[] = '';
        }
        $data[] = $totRow;

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
        $signatureLine1 = array_fill(0, $cols, '');
        $signatureLine1[$cols - 4] = 'LE CHEF DE BUREAU PAX BUS ai';
        $data[] = $signatureLine1;

        $signatureLine2 = array_fill(0, $cols, '');
        $signatureLine2[$cols - 4] = 'FREDDY KALEMA TABU';
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
                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.25);
                $s->getPageMargins()->setRight(0.25);

                // ✅ CALCUL DES INDICES CORRECTS
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);
                
                $headerRow = 6;
                $firstDataRow = $headerRow + 1;
                $lastDataRow = 6 + count($this->rows['pax']);
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
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
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
                $s->getStyle('A5')
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

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
                    ->setBorderStyle(Border::BORDER_MEDIUM);

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
                    ->setBorderStyle(Border::BORDER_THICK);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THICK);

                // ═══════════════════════════════════════════════════════════
                // HAUTEUR DES LIGNES
                // ═══════════════════════════════════════════════════════════
                
                $s->getRowDimension($headerRow)->setRowHeight(25);
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ═══════════════════════════════════════════════════════════
                // ✅ SIGNATURE (2 colonnes depuis la droite, fusionnées)
                // ═══════════════════════════════════════════════════════════
                
                $signatureRow1 = $totalsRow + 2;
                $signatureRow2 = $signatureRow1 + 1;

                // 2 colonnes à partir de la droite
                $signatureStartCol = Coordinate::stringFromColumnIndex($highestColIndex - 3);
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                // Ligne 1 : LE CHEF DE BUREAU PAX BUS ai
                $s->mergeCells("{$signatureStartCol}{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : FREDDY KALEMA TABU
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
}