<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PaxbusDomesticStatSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected $sheetTitle;

    protected $title;

    protected $rows;

    protected $operators;

    protected $daysInMonth;

    public function __construct(string $sheetTitle, string $title, array $rows, $operators)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->rows = $rows;
        $this->operators = $operators;
        $this->daysInMonth = count($rows['pax']);
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    protected function headings(): array
    {
        $headers = ['DATE'];

        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $headers[] = (string) $day;
        }

        $headers[] = 'TOT';
        $headers[] = 'PMAD';

        return $headers;
    }

    public function array(): array
    {
        $headings = $this->headings();
        $cols = count($headings);
        $data = [];

        // TITRES
        foreach ([
            ['SERVICE VTA'],
            ['BUREAU PAX BUS'],
            ["RVA AERO/N'DJILI"],
            [''],
            [$this->title],
        ] as $line) {
            $data[] = array_pad($line, $cols, '');
        }

        // EN-TÊTES
        $data[] = $headings;

        // OPÉRATEURS ORGANISÉS PAR PMAD
        $operatorsData = $this->organizeOperatorsByPMAD();

        foreach ($operatorsData as $categoryData) {
            foreach ($categoryData as $operatorData) {

                // LIGNE OPÉRATEUR
                $operatorRow = [$operatorData['sigle']];
                for ($i = 1; $i < $cols; $i++) {
                    $operatorRow[] = '';
                }
                $data[] = $operatorRow;

                // LIGNES AÉRONEFS
                foreach ($operatorData['aircrafts'] as $aircraftData) {
                    $row = [$aircraftData['immatriculation']];

                    foreach ($aircraftData['daily_counts'] as $count) {
                        $row[] = (int) $count;
                    }

                    $row[] = ''; // TOTAL (formule)
                    $row[] = $aircraftData['category']; // PMAD

                    $data[] = $row;
                }
            }
        }

        // LIGNE TOTAL GÉNÉRAL
        $totRow = ['TOTAL'];
        for ($i = 1; $i < $cols - 1; $i++) {
            $totRow[] = '';
        }
        $totRow[] = '';
        $data[] = $totRow;

        // SIGNATURE
        $data[] = array_fill(0, $cols, '');
        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 6] = 'LE CHEF DE BUREAU PAX BUS ai';
        $data[] = $sig1;

        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 6] = 'FREDDY KALEMA TABU';
        $data[] = $sig2;

        return $data;
    }

    /**
     * ORGANISATION PAR PMAD
     */
    private function organizeOperatorsByPMAD(): array
    {
        $heavy = [];
        $light = [];

        foreach ($this->rows['pax'] as $dayIndex => $dayData) {
            foreach ($this->operators as $operatorSigle) {
                if (! isset($dayData[$operatorSigle])) {
                    continue;
                }

                foreach ($dayData[$operatorSigle] as $immatriculation => $aircraftData) {
                    $pmad = $aircraftData['pmad'];

                    $targetArray = $pmad >= 50000 ? $heavy : $light;
                    $category = $pmad >= 50000 ? '≥50T' : '<50T';

                    if (! isset($targetArray[$operatorSigle])) {
                        $targetArray[$operatorSigle] = [
                            'sigle' => $operatorSigle,
                            'aircrafts' => [],
                        ];
                    }

                    if (! isset($targetArray[$operatorSigle]['aircrafts'][$immatriculation])) {
                        $targetArray[$operatorSigle]['aircrafts'][$immatriculation] = [
                            'immatriculation' => $immatriculation,
                            'pmad' => $pmad,
                            'category' => $category,
                            'daily_counts' => array_fill(0, $this->daysInMonth, 0),
                        ];
                    }

                    $targetArray[$operatorSigle]['aircrafts'][$immatriculation]['daily_counts'][$dayIndex]
                        = $aircraftData['count'];

                    $pmad >= 50000 ? $heavy = $targetArray : $light = $targetArray;
                }
            }
        }

        return [$heavy, $light];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $s = $event->sheet->getDelegate();

                // PAGE
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                // MARGES
                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.5);
                $s->getPageMargins()->setRight(0.5);

                // CALCUL DES INDICES
                $highestRow = $s->getHighestRow();
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                $headerRow = 6;
                $firstDataRow = $headerRow + 1;

                // COLONNE PMAD (DERNIÈRE)
                $pmadColIndex = $highestColIndex;
                $pmadColLetter = Coordinate::stringFromColumnIndex($pmadColIndex);

                // TROUVER LIGNE TOTAL
                $totalsRow = null;
                for ($r = $firstDataRow; $r <= $highestRow; $r++) {
                    if ($s->getCell("A{$r}")->getValue() === 'TOTAL') {
                        $s->getRowDimension($r)->setRowHeight(20);
                        $totalsRow = $r;
                        break;
                    }
                }

                $lastDataRow = $totalsRow - 1;
                $totColIndex = $highestColIndex - 1;
                $totColLetter = Coordinate::stringFromColumnIndex($totColIndex);

                // STYLE DES TITRES (Lignes 1-5)

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
                $s->getStyle('A5')
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // Style entete

                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // STYLING DES DONNÉES ET BORDURES

                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {

                    $firstColValue = $s->getCell("A{$row}")->getValue();
                    $pmadValue = $s->getCell("{$pmadColLetter}{$row}")->getValue();

                    // ✅ LIGNE OPÉRATEUR
                    if (! empty($firstColValue) && empty($pmadValue)) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getFont()->setBold(true);

                        continue;
                    }

                    // LIGNE VIDE
                    if (empty($firstColValue)) {
                        continue;
                    }

                    // ✅ LIGNE AÉRONEF
                    if (! empty($pmadValue)) {

                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getBorders()->getAllBorders()
                            ->setBorderStyle(Border::BORDER_THIN);
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                        // ✅ AJOUT : Forcer l'affichage des 0 avec setCellValueExplicit
                        for ($col = 2; $col < $totColIndex; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $cellValue = $s->getCell("{$colLetter}{$row}")->getValue();

                            $s->setCellValueExplicit(
                                "{$colLetter}{$row}",
                                (int) $cellValue,
                                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                            );

                            // Format
                            $s->getStyle("{$colLetter}{$row}")
                                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $s->getStyle("{$colLetter}{$row}")
                                ->getNumberFormat()->setFormatCode('0');
                        }

                        // TOTAL FORMULE
                        $firstDayCol = Coordinate::stringFromColumnIndex(2);
                        $lastDayCol = Coordinate::stringFromColumnIndex($totColIndex - 1);

                        $s->setCellValue(
                            "{$totColLetter}{$row}",
                            "=SUM({$firstDayCol}{$row}:{$lastDayCol}{$row})"
                        );
                    }
                }

                // TOTAL GÉNÉRAL
                if ($totalsRow) {

                    // ✅ AJOUT : Bordures sur toute la ligne TOTAL (colonne A jusqu'à la fin)
                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getFont()->setBold(true)->setSize(12);
                    for ($col = 2; $col <= $totColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cells = [];

                        for ($r = $firstDataRow; $r < $totalsRow; $r++) {
                            if (! empty($s->getCell("{$pmadColLetter}{$r}")->getValue())) {
                                $cells[] = "{$colLetter}{$r}";
                            }
                        }

                        if ($cells) {
                            $s->setCellValue("{$colLetter}{$totalsRow}", '=SUM('.implode(',', $cells).')');
                        }

                        $s->getStyle("{$colLetter}{$totalsRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $s->getStyle("{$colLetter}{$totalsRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $s->getStyle("{$colLetter}{$totalsRow}")
                            ->getNumberFormat()->setFormatCode('#,##0');

                    }
                }

                // ═══════════════════════════════════════════════════════════
                // HAUTEUR DES LIGNES
                // ═══════════════════════════════════════════════════════════

                $s->getRowDimension($headerRow)->setRowHeight(22);
                if ($totalsRow) {
                    $s->getRowDimension($totalsRow)->setRowHeight(18);
                }

                // ═══════════════════════════════════════════════════════════
                // SIGNATURE
                // ═══════════════════════════════════════════════════════════

                $signatureRow1 = $totalsRow + 2;
                $signatureRow2 = $signatureRow1 + 1;

                $signatureStartCol = Coordinate::stringFromColumnIndex($highestColIndex - 5);
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                // Ligne 1
                $s->mergeCells("{$signatureStartCol}{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2
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
