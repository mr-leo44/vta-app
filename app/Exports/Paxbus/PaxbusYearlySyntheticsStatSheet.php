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

/**
 * Feuille annuelle PAX BUS (récapitulatif mensuel).
 *
 * Colonnes :
 *   A  MOIS
 *   B  PAX INTERNATIONAL          ← brut
 *   C  AERONEFS NAT ≥ 50T         ← brut
 *   D  AERONEFS NAT < 50T         ← brut
 *   E  TOTAL DOM                  = C + D   ← formule Excel
 *
 * Ligne TOTAL :
 *   B,C,D,E = SUM(...)            ← formules Excel
 */
class PaxbusYearlySyntheticsStatSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $domesticData;
    protected array  $internationalData;

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $domesticData,
        array  $internationalData
    ) {
        $this->sheetTitle        = $sheetTitle;
        $this->title             = $title;
        $this->domesticData      = $domesticData;
        $this->internationalData = $internationalData;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    public function array(): array
    {
        $cols = 5;
        $data = [];

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

        // En-têtes (2 lignes)
        $data[] = ['MOIS', 'NOMBRES DES PAX INTERNATIONAL', 'PMAD POUR LES AERONEFS NATIONAUX', '', ''];
        $data[] = ['', '', '≥ 50 TONNES', '< 50 TONNES', 'TOTAL'];

        // Données brutes par mois
        foreach ($this->domesticData['pax'] as $monthIndex => $domesticMonthData) {
            $date       = $this->getMonthName($domesticMonthData['date'] ?? '');
            $heavyCount = 0;
            $lightCount = 0;

            foreach ($domesticMonthData as $operatorSigle => $aircrafts) {
                if ($operatorSigle === 'date') continue;
                foreach ($aircrafts as $aircraftData) {
                    $pmad  = $aircraftData['pmad']  ?? 0;
                    $count = $aircraftData['count'] ?? 0;
                    if ($pmad >= 50000) {
                        $heavyCount += $count;
                    } else {
                        $lightCount += $count;
                    }
                }
            }

            $intlRow = $this->internationalData['pax'][$monthIndex] ?? [];
            $intlPax = 0;
            foreach ($intlRow as $key => $value) {
                if ($key === 'date') continue;
                $intlPax += (int) $value;
            }

            $data[] = [
                $date,        // A
                $intlPax,     // B ← brut
                $heavyCount,  // C ← brut
                $lightCount,  // D ← brut
                '',           // E ← formule C+D
            ];
        }

        $data[] = ['TOTAUX', '', '', '', ''];
        $data[] = array_fill(0, $cols, '');

        $sig1            = array_fill(0, $cols, '');
        $sig1[$cols - 4] = 'LE CHEF DE BUREAU PAX BUS ai';
        $data[]          = $sig1;

        $sig2            = array_fill(0, $cols, '');
        $sig2[$cols - 4] = 'FREDDY KALEMA TABU';
        $data[]          = $sig2;

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
                $s->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.5)->setRight(0.5);

                $highestRow   = $s->getHighestRow();
                $highestCol   = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);
                $headerRow1   = 6;
                $headerRow2   = 7;
                $firstDataRow = 8;

                // Trouver ligne TOTAUX
                $totalsRow = null;
                for ($r = $firstDataRow; $r <= $highestRow; $r++) {
                    if ($s->getCell("A{$r}")->getValue() === 'TOTAUX') {
                        $totalsRow = $r;
                        break;
                    }
                }
                $lastDataRow = $totalsRow - 1;

                // ── Formule E = C + D par ligne ───────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getCell("E{$row}")->setValue("=C{$row}+D{$row}");

                    foreach (['B', 'C', 'D'] as $col) {
                        $s->setCellValueExplicit(
                            "{$col}{$row}",
                            (int) $s->getCell("{$col}{$row}")->getValue(),
                            DataType::TYPE_NUMERIC
                        );
                        $s->getStyle("{$col}{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("{$col}{$row}")
                            ->getNumberFormat()->setFormatCode('0');
                    }
                    $s->getStyle("A{$row}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // ── Bordures données ──────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // ── Ligne TOTAUX ──────────────────────────────────────────
                if ($totalsRow) {
                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                        ->getFont()->setBold(true)->setSize(12);

                    foreach (['B', 'C', 'D'] as $col) {
                        $s->getCell("{$col}{$totalsRow}")
                            ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastDataRow})");
                        $s->getStyle("{$col}{$totalsRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("{$col}{$totalsRow}")
                            ->getNumberFormat()->setFormatCode('0');
                    }
                    $s->getCell("E{$totalsRow}")->setValue("=C{$totalsRow}+D{$totalsRow}");
                    $s->getStyle("E{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ── Styles titres ─────────────────────────────────────────
                for ($row = 1; $row <= 3; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(10);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(12);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle('A5')->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // ── En-têtes ──────────────────────────────────────────────
                $s->mergeCells("A{$headerRow1}:A{$headerRow2}");
                $s->mergeCells("B{$headerRow1}:B{$headerRow2}");
                $s->mergeCells("C{$headerRow1}:E{$headerRow1}");

                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow2}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $s->getRowDimension($headerRow1)->setRowHeight(22);
                $s->getRowDimension($headerRow2)->setRowHeight(22);
                if ($totalsRow) $s->getRowDimension($totalsRow)->setRowHeight(20);

                // ── Signature ─────────────────────────────────────────────
                $signatureRow1 = $totalsRow + 2;
                $signatureRow2 = $signatureRow1 + 1;
                $sigStart      = Coordinate::stringFromColumnIndex($highestColIndex - 2);
                $sigEnd        = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("{$sigStart}{$signatureRow1}:{$sigEnd}{$signatureRow1}");
                $s->getStyle("{$sigStart}{$signatureRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$sigStart}{$signatureRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->mergeCells("{$sigStart}{$signatureRow2}:{$sigEnd}{$signatureRow2}");
                $s->getStyle("{$sigStart}{$signatureRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$sigStart}{$signatureRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function getMonthName(string $month): string
    {
        // Format attendu : 'MM-YYYY'
        $parts      = explode('-', $month);
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
        return $monthNames[$parts[0]] ?? $month;
    }
}
