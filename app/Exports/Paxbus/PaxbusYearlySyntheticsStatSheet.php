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

class PaxbusYearlySyntheticsStatSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected $sheetTitle;
    protected $title;
    protected $subTitle;
    protected $domesticData;
    protected $internationalData;

    public function __construct(
        string $sheetTitle,
        string $title,
        array $domesticData,
        array $internationalData
    ) {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->domesticData = $domesticData;
        $this->internationalData = $internationalData;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    public function array(): array
    {
        $cols = 6; // DATE + 3 domestic + 1 international + TOTAL
        $data = [];

        // TITRES
        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU PAX BUS'],
                ["RVA AERO/N'DJILI"],
                [''],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        // EN-TÊTES LIGNE 1 (avec merge)
        $header1 = ['MOIS', 'NOMBRES DES PAX INTERNATIONAL', 'PMAD POUR LES AERONEFS NATIONAUX', '', ''];
        $data[] = $header1;

        // EN-TÊTES LIGNE 2 (sous-colonnes)
        $header2 = ['', '', '≥ 50 TONNES', '< 50 TONNES', ''];
        $data[] = $header2;

        // DONNÉES PAR JOUR
        foreach ($this->domesticData['pax'] as $monthIndex => $domesticMonthData) {
            // $date = $domesticDayData['date'] ?? '';
            $date = $this->getMonthName($domesticMonthData['date']) ?? '';

            // Compter les vols domestiques
            $heavyCount = 0;
            $lightCount = 0;

            foreach ($domesticMonthData as $operatorSigle => $aircrafts) {
                if ($operatorSigle === 'date') continue;

                foreach ($aircrafts as $aircraftData) {
                    $pmad = $aircraftData['pmad'] ?? 0;
                    $count = $aircraftData['count'] ?? 0;

                    if ($pmad >= 50000) {
                        $heavyCount += $count;
                    } else {
                        $lightCount += $count;
                    }
                }
            }

            $totalDomestic = $heavyCount + $lightCount;

            // Compter les pax internationaux
            $internationalRow = $this->internationalData['pax'][$monthIndex] ?? [];
            $totalPaxInternational = 0;

            foreach ($internationalRow as $key => $value) {
                if ($key === 'date') continue;
                $totalPaxInternational += (int)$value;
            }

            $row = [
                $date,
                (int)$totalPaxInternational,
                (int)$heavyCount,
                (int)$lightCount,
                (int)$totalDomestic
            ];

            $data[] = $row;
        }

        // LIGNE TOTAUX
        $totRow = ['TOTAUX', '', '', '', ''];
        $data[] = $totRow;

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 4] = 'LE CHEF DE BUREAU PAX BUS ai';
        $data[] = $sig1;

        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 4] = 'FREDDY KALEMA TABU';
        $data[] = $sig2;

        return $data;
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

                $headerRow1 = 6;
                $headerRow2 = 7;
                $firstDataRow = 8;

                // TROUVER LIGNE TOTAL
                $totalsRow = null;
                for ($r = $firstDataRow; $r <= $highestRow; $r++) {
                    if ($s->getCell("A{$r}")->getValue() === 'TOTAUX') {
                        $totalsRow = $r;
                        break;
                    }
                }

                $lastDataRow = $totalsRow - 1;

                // STYLE DES TITRES (Lignes 1-5)
                for ($row = 1; $row <= 3; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(10);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Ligne 5 : TITRE PRINCIPAL
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(12);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle('A5')
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // EN-TÊTES LIGNE 1 : Merge et style
                // DATE (A6)
                $s->mergeCells("A{$headerRow1}:A{$headerRow2}");

                // VOLS INTERNATIONAUX (B6:B7)
                $s->mergeCells("B{$headerRow1}:B{$headerRow2}");

                // VOLS DOMESTIQES (B6:B7)
                $s->mergeCells("C{$headerRow1}:E{$headerRow1}");

                // Style en-têtes
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // DONNÉES : Bordures et alignement
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    // Colonne DATE : alignée à gauche
                    $s->getStyle("A{$row}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Colonnes numériques : alignées à droite avec setCellValueExplicit
                    for ($col = 2; $col <= $highestColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $cellValue = $s->getCell("{$colLetter}{$row}")->getValue();

                        $s->setCellValueExplicit(
                            "{$colLetter}{$row}",
                            (int)$cellValue,
                            DataType::TYPE_NUMERIC
                        );

                        $s->getStyle("{$colLetter}{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("{$colLetter}{$row}")
                            ->getNumberFormat()->setFormatCode('0');
                    }
                }

                // LIGNE TOTAUX
                if ($totalsRow) {
                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getFont()->setBold(true)->setSize(12);

                    // Formules pour les totaux
                    for ($col = 2; $col <= $highestColIndex; $col++) {
                        $colLetter = Coordinate::stringFromColumnIndex($col);
                        $s->setCellValue(
                            "{$colLetter}{$totalsRow}",
                            "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$lastDataRow})"
                        );

                        $s->getStyle("{$colLetter}{$totalsRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("{$colLetter}{$totalsRow}")
                            ->getNumberFormat()->setFormatCode('0');
                    }
                }

                // HAUTEUR DES LIGNES
                $s->getRowDimension($headerRow1)->setRowHeight(22);
                $s->getRowDimension($headerRow2)->setRowHeight(22);
                if ($totalsRow) {
                    $s->getRowDimension($totalsRow)->setRowHeight(20);
                }

                // SIGNATURE
                $signatureRow1 = $totalsRow + 2;
                $signatureRow2 = $signatureRow1 + 1;

                $signatureStartCol = Coordinate::stringFromColumnIndex($highestColIndex - 2);
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
            '01-2026' => 'JANVIER',
            '02-2026' => 'FÉVRIER',
            '03-2026' => 'MARS',
            '04-2026' => 'AVRIL',
            '05-2026' => 'MAI',
            '06-2026' => 'JUIN',
            '07-2026' => 'JUILLET',
            '08-2026' => 'AOÛT',
            '09-2026' => 'SEPTEMBRE',
            '10-2026' => 'OCTOBRE',
            '11-2026' => 'NOVEMBRE',
            '12-2026' => 'DÉCEMBRE',
        ];
        return $monthNames[$month] ?? '';
    }
}
