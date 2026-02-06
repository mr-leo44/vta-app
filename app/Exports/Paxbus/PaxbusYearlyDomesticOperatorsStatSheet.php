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

class PaxbusYearlyDomesticOperatorsStatSheet implements FromArray, ShouldAutoSize, WithTitle, WithEvents
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

        for ($i = 1; $i <= 12; $i++) {
            $headers[] = $this->getMonthName($i);
            $headers[] = "";
        }

        $headers[] = 'TOTAL';
        $headers[] = "";

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
        $data[] = ['CIES'];
        $firstArrayPMAD = ['PMAD'];
        $secondArray = [];
        for ($i = 1; $i <= 12; $i++) { 
            $secondArray[] = "≥ 50T";
            $secondArray[] = "< 50T";
        }
        // Ajouter aussi les labels pour les colonnes TOTAL
        $secondArray[] = "≥ 50T";
        $secondArray[] = "< 50T";
        $data[] = array_merge($firstArrayPMAD, $secondArray);

        // Pour chaque opérateur, créer une ligne avec les données mensuelles
        foreach ($this->operators as $operator) {
            $dataRow = [$operator];

            // Pour chaque mois
            for ($monthNum = 1; $monthNum <= 12; $monthNum++) {
                $countGte50 = 0;  // ≥ 50T
                $countLt50 = 0;   // < 50T

                // Chercher les données de ce mois et opérateur
                foreach ($this->rows['pax'] as $row) {
                    if ((int)$row['date'] === $monthNum && isset($row[$operator])) {
                        foreach ($row[$operator] as $aircraftData) {
                            $pmad = $aircraftData['pmad'] ?? 0;
                            if ($pmad >= 50000) {
                                $countGte50 += $aircraftData['count'];
                            } else {
                                $countLt50 += $aircraftData['count'];
                            }
                        }
                    }
                }

                $dataRow[] = $countGte50;
                $dataRow[] = $countLt50;
            }

            // Colonne TOTAL (sera calculée par formule Excel)
            $dataRow[] = '';
            $dataRow[] = '';

            $data[] = $dataRow;
        }

        $totRow = ["TOTAL"];
        for ($i = 1; $i < $cols; $i++) {
            $totRow[] = '';
        }
        $data[] = $totRow;

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');
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
                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.25);
                $s->getPageMargins()->setRight(0.25);

                // ✅ CALCUL DES INDICES CORRECTS
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                $headerRow = 7;
                $firstDataRow = 10;  // Première ligne d'opérateur
                $lastDataRow = 9 + count($this->operators);  // Dernière ligne d'opérateur
                $totalsRow = $lastDataRow + 1;

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
                    ->getFont()->setBold(true)->setSize(13);
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

                
                // ===========================================================
                // Merge des colonnes de l'en tete
                // ===========================================================

                $s->mergeCells("B{$headerRow}:C{$headerRow}");
                $s->mergeCells("D{$headerRow}:E{$headerRow}");
                $s->mergeCells("F{$headerRow}:G{$headerRow}");
                $s->mergeCells("H{$headerRow}:I{$headerRow}");
                $s->mergeCells("J{$headerRow}:K{$headerRow}");
                $s->mergeCells("L{$headerRow}:M{$headerRow}");
                $s->mergeCells("N{$headerRow}:O{$headerRow}");
                $s->mergeCells("P{$headerRow}:Q{$headerRow}");
                $s->mergeCells("R{$headerRow}:S{$headerRow}");
                $s->mergeCells("T{$headerRow}:U{$headerRow}");
                $s->mergeCells("V{$headerRow}:W{$headerRow}");
                $s->mergeCells("X{$headerRow}:Y{$headerRow}");
                $s->mergeCells("Z{$headerRow}:AA{$headerRow}");
                $s->mergeCells("B" . ($headerRow + 1) . ":AA" . ($headerRow + 1)    );

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

                // Colonne MOIS/Opérateur : alignée à gauche
                $s->getStyle("A{$firstDataRow}:A{$lastDataRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Mise en gras de la colonne A (opérateurs)
                $s->getStyle("A{$firstDataRow}:A{$lastDataRow}")
                    ->getFont()->setBold(true);

                // Colonnes numériques : alignées à droite avec format nombre
                for ($col = 2; $col < $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0');
                }

                // ═══════════════════════════════════════════════════════════
                // REMPLISSAGE DES CELLULES VIDES AVEC 0
                // ═══════════════════════════════════════════════════════════

                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    for ($col = 2; $col <= $highestColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cellRef = "{$colLetter}{$row}";
                        
                        // Si la cellule est vide, la remplir avec 0
                        if ($s->getCell($cellRef)->getValue() === null || $s->getCell($cellRef)->getValue() === '') {
                            $s->setCellValueExplicit(
                                $cellRef,
                                0,
                                DataType::TYPE_NUMERIC
                            );
                        }
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // ✅ FORMULES EXCEL POUR LES TOTAUX
                // ═══════════════════════════════════════════════════════════

                // La colonne TOTAL est la colonne Z (index 26)
                $totalColLetter = 'Z';  // Colonne TOTAL
                $firstDataColLetter = 'B';  // Première colonne de données
                $lastDataColLetter = 'Y';   // Dernière colonne avant TOTAL

                // Formules horizontales : somme des opérateurs par mois pour chaque row
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->setCellValue(
                        "{$totalColLetter}{$row}",
                        "=SUM({$firstDataColLetter}{$row}:{$lastDataColLetter}{$row})"
                    );
                }

                // Formules verticales : somme de chaque colonne pour la ligne TOTAL
                for ($col = 2; $col <= 27; $col++) {  // Colonnes B à AA
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    
                    // Colonnes Z et AA : formules spéciales
                    if ($colLetter === 'Z') {
                        // Z: somme des colonnes ≥ 50T (B, D, F, H, J, L, N, P, R, T, V, X)
                        $s->setCellValue(
                            "{$colLetter}{$totalsRow}",
                            "=B{$totalsRow}+D{$totalsRow}+F{$totalsRow}+H{$totalsRow}+J{$totalsRow}+L{$totalsRow}+N{$totalsRow}+P{$totalsRow}+R{$totalsRow}+T{$totalsRow}+V{$totalsRow}+X{$totalsRow}"
                        );
                    } elseif ($colLetter === 'AA') {
                        // AA: somme des colonnes < 50T (C, E, G, I, K, M, O, Q, S, U, W, Y)
                        $s->setCellValue(
                            "{$colLetter}{$totalsRow}",
                            "=C{$totalsRow}+E{$totalsRow}+G{$totalsRow}+I{$totalsRow}+K{$totalsRow}+M{$totalsRow}+O{$totalsRow}+Q{$totalsRow}+S{$totalsRow}+U{$totalsRow}+W{$totalsRow}+Y{$totalsRow}"
                        );
                    } else {
                        // Autres colonnes : somme verticale standard
                        $s->setCellValue(
                            "{$colLetter}{$totalsRow}",
                            "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow})"
                        );
                    }
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
                $s->getStyle("B{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // ═══════════════════════════════════════════════════════════
                // TOTAL GÉNÉRAL (Ligne suivante)
                // ═══════════════════════════════════════════════════════════

                $totalGeneralRow = $totalsRow + 1;
                $s->setCellValue("A{$totalGeneralRow}", "TOTAL GÉNÉRAL");
                $s->getStyle("A{$totalGeneralRow}")->getFont()->setBold(true)->setSize(12);
                $s->mergeCells("A{$totalGeneralRow}:Z{$totalGeneralRow}");
                $s->getStyle("A{$totalGeneralRow}:Z{$totalGeneralRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Formule dans AA : somme de Z et AA
                $s->setCellValue(
                    "AA{$totalGeneralRow}",
                    "=Z{$totalsRow}+AA{$totalsRow}"
                );
                $s->getStyle("AA{$totalGeneralRow}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("AA{$totalGeneralRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $s->getStyle("AA{$totalGeneralRow}")->getNumberFormat()->setFormatCode('0');

                // Bordures de la ligne TOTAL GÉNÉRAL
                $s->getStyle("A{$totalGeneralRow}:{$highestCol}{$totalGeneralRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // ═══════════════════════════════════════════════════════════
                // HAUTEUR DES LIGNES
                // ═══════════════════════════════════════════════════════════

                $s->getRowDimension($headerRow)->setRowHeight(20);
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ═══════════════════════════════════════════════════════════
                // ✅ SIGNATURE (2 colonnes depuis la droite, fusionnées)
                // ═══════════════════════════════════════════════════════════

                $signatureRow1 = $totalGeneralRow + 2;
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
        $monthNames = [
            '1' => 'JAN',
            '2' => 'FEV',
            '3' => 'MARS',
            '4' => 'AVRIL',
            '5' => 'MAI',
            '6' => 'JUIN',
            '7' => 'JUIL',
            '8' => 'AOÛT',
            '9' => 'SEPT',
            '10' => 'OCT',
            '11' => 'NOV',
            '12' => 'DEC',
        ];
        return $monthNames[$month] ?? '';
    }
}
