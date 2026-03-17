<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Single sheet for the unified traffic report.
 *
 * Domestic columns (7 data cols):
 *   A        DATE/MOIS
 *   B        PAX Trafic
 *   C        PAX Go-Pass
 *   D        Fret Trafic
 *   E        Fret IDEF
 *   F        Excédents Trafic
 *   G        Excédents IDEF
 *   H        TOTAL PAX (formula)
 *
 * International columns (11 data cols):
 *   A        DATE/MOIS
 *   B        PAX Trafic
 *   C        PAX Go-Pass
 *   D        PAX Bus
 *   E        Fret Dép. Trafic
 *   F        Fret Dép. IDEF
 *   G        Fret Arr. Trafic
 *   H        Fret Arr. IDEF
 *   I        Excéd. Dép. Trafic
 *   J        Excéd. Dép. IDEF
 *   K        Excéd. Arr. Trafic
 *   L        Excéd. Arr. IDEF
 */
class VTATrafficReportSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $pax;
    protected array  $fret;         // domestic: fret[]  /  international: fret_depart[]
    protected array  $excedents;    // domestic: excedents[]  /  international: exced_depart[]
    protected array  $fretArrivee;  // international only
    protected array  $excedArrivee; // international only
    protected bool   $isAnnual;
    protected bool   $isInternational;

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $pax,
        array  $fret,
        array  $excedents,
        bool   $isInternational = false,
        array  $fretArrivee     = [],
        array  $excedArrivee    = [],
        bool   $isAnnual        = false
    ) {
        $this->sheetTitle      = $sheetTitle;
        $this->title           = $title;
        $this->pax             = $pax;
        $this->fret            = $fret;
        $this->excedents       = $excedents;
        $this->fretArrivee     = $fretArrivee;
        $this->excedArrivee    = $excedArrivee;
        $this->isInternational = $isInternational;
        $this->isAnnual        = $isAnnual;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // array() — raw data, all computed columns left empty (filled in AfterSheet)
    // ─────────────────────────────────────────────────────────────────────────
    public function array(): array
    {
        $cols = $this->isInternational ? 12 : 8;
        $data = [];

        // Document header (rows 1-4)
        foreach ([
            ['SERVICE VTA'],
            ["RVA AERO/N'DJILI"],
            ['DIVISION COMMERCIALE'],
        ] as $line) {
            $data[] = array_pad($line, $cols, '');
        }

        // Title row (row 5 - merged in AfterSheet)
        $data[] = array_pad([$this->title], $cols, '');

        // Header rows (rows 6-7: group labels + sub-labels)
        if ($this->isInternational) {
            $data[] = [
                $this->isAnnual ? 'MOIS' : 'DATE',
                'PAX', '', '',
                'FRET DÉPART', '',
                'FRET ARRIVÉE', '',
                'EXCÉD. DÉPART', '',
                'EXCÉD. ARRIVÉE', '',
            ];
            $data[] = [
                '',
                'Trafic', 'Go-Pass', 'PAX Bus',
                'Trafic', 'IDEF',
                'Trafic', 'IDEF',
                'Trafic', 'IDEF',
                'Trafic', 'IDEF',
            ];
        } else {
            $data[] = [
                $this->isAnnual ? 'MOIS' : 'DATE',
                'PAX', '',
                'FRET', '',
                'EXCÉDENTS', '',
                'TOTAL PAX',
            ];
            $data[] = ['', 'Trafic', 'Go-Pass', 'Trafic', 'IDEF', 'Trafic', 'IDEF', ''];
        }

        // Data rows — only raw brut values, computed cols empty
        $count = count($this->pax);
        for ($i = 0; $i < $count; $i++) {
            $paxRow   = $this->pax[$i]   ?? [];
            $fretRow  = $this->fret[$i]  ?? [];
            $excedRow = $this->excedents[$i] ?? [];

            $label = $paxRow['DATE'] ?? $paxRow['MOIS'] ?? '';

            if ($this->isInternational) {
                $fretArrRow  = $this->fretArrivee[$i]  ?? [];
                $excedArrRow = $this->excedArrivee[$i] ?? [];
                $data[] = [
                    $label,
                    (int) ($paxRow['traffic']  ?? 0),
                    (int) ($paxRow['gopass']   ?? 0),
                    (int) ($paxRow['paxbus']   ?? 0),
                    (int) ($fretRow['traffic'] ?? 0),
                    (int) ($fretRow['idef']    ?? 0),
                    (int) ($fretArrRow['traffic'] ?? 0),
                    (int) ($fretArrRow['idef']    ?? 0),
                    (int) ($excedRow['traffic']    ?? 0),
                    (int) ($excedRow['idef']       ?? 0),
                    (int) ($excedArrRow['traffic']  ?? 0),
                    (int) ($excedArrRow['idef']     ?? 0),
                ];
            } else {
                $data[] = [
                    $label,
                    (int) ($paxRow['traffic']  ?? 0),
                    (int) ($paxRow['gopass']   ?? 0),
                    (int) ($fretRow['traffic'] ?? 0),
                    (int) ($fretRow['idef']    ?? 0),
                    (int) ($excedRow['traffic'] ?? 0),
                    (int) ($excedRow['idef']    ?? 0),
                    '', // TOTAL PAX = formula
                ];
            }
        }

        // Total row
        $data[] = array_pad(['TOTAL'], $cols, '');

        // Signature
        $data[] = array_fill(0, $cols, '');
        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 3] = 'LE CHEF DE SERVICE VTA';
        $data[] = $sig1;
        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 3] = 'MINSAY NKASER SAGESSE';
        $data[] = $sig2;

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // registerEvents() — styles + formulas
    // ─────────────────────────────────────────────────────────────────────────
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

                $headerRow1   = 6;   // Group label row
                $headerRow2   = 7;   // Sub-column row
                $firstDataRow = 8;
                $lastDataRow  = $highestRow - 4; // before TOTAL
                $totalsRow    = $lastDataRow + 1;

                // ── Document header rows 1-4 ──────────────────────────────
                for ($r = 1; $r <= 4; $r++) {
                    $s->mergeCells("A{$r}:{$highestCol}{$r}");
                    $s->getStyle("A{$r}")->getFont()->setBold(false)->setSize(11);
                    $s->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // ── Title row 5 ───────────────────────────────────────────
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(13);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD9E1F2');
                $s->getRowDimension(5)->setRowHeight(22);

                // ── Group header row 6 ────────────────────────────────────
                $headerColor = $this->isInternational ? 'FF3949AB' : 'FF1565C0';
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($headerColor);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Merge group headers
                if ($this->isInternational) {
                    $s->mergeCells("A{$headerRow1}:A{$headerRow2}"); // DATE
                    $s->mergeCells("B{$headerRow1}:D{$headerRow1}"); // PAX
                    $s->mergeCells("E{$headerRow1}:F{$headerRow1}"); // FRET DEP
                    $s->mergeCells("G{$headerRow1}:H{$headerRow1}"); // FRET ARR
                    $s->mergeCells("I{$headerRow1}:J{$headerRow1}"); // EXCED DEP
                    $s->mergeCells("K{$headerRow1}:L{$headerRow1}"); // EXCED ARR
                } else {
                    $s->mergeCells("A{$headerRow1}:A{$headerRow2}"); // DATE
                    $s->mergeCells("B{$headerRow1}:C{$headerRow1}"); // PAX
                    $s->mergeCells("D{$headerRow1}:E{$headerRow1}"); // FRET
                    $s->mergeCells("F{$headerRow1}:G{$headerRow1}"); // EXCÉDENTS
                    $s->mergeCells("H{$headerRow1}:H{$headerRow2}"); // TOTAL PAX
                }

                // ── Sub-header row 7 ──────────────────────────────────────
                $subHeaderColor = $this->isInternational ? 'FF5C6BC0' : 'FF1976D2';
                $s->getStyle("A{$headerRow2}:{$highestCol}{$headerRow2}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($subHeaderColor);
                $s->getStyle("A{$headerRow2}:{$highestCol}{$headerRow2}")
                    ->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow2}:{$highestCol}{$headerRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $s->getRowDimension($headerRow1)->setRowHeight(20);
                $s->getRowDimension($headerRow2)->setRowHeight(18);

                // ── Borders on entire table ────────────────────────────────
                $s->getStyle("A{$headerRow1}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Data rows: force numeric, alternating bg ───────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    // Alternating row color
                    if (($row - $firstDataRow) % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF5F5F5');
                    }

                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Force numeric on all data columns (B onwards)
                    for ($col = 2; $col <= $highestColIndex; $col++) {
                        $cl    = Coordinate::stringFromColumnIndex($col);
                        $value = $s->getCell("{$cl}{$row}")->getValue();
                        if ($value !== '' && $value !== null) {
                            $s->setCellValueExplicit("{$cl}{$row}", (int) $value, DataType::TYPE_NUMERIC);
                        }
                        $s->getStyle("{$cl}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    }

                    // TOTAL PAX formula (domestic only): =B+C
                    if (!$this->isInternational) {
                        $s->getCell("H{$row}")->setValue("=B{$row}+C{$row}");
                    }
                }

                // ── SUM formulas on TOTAL row ──────────────────────────────
                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $cl = Coordinate::stringFromColumnIndex($col);
                    $s->getCell("{$cl}{$totalsRow}")
                      ->setValue("=SUM({$cl}{$firstDataRow}:{$cl}{$lastDataRow})");
                    $s->getStyle("{$cl}{$totalsRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("{$cl}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // ── TOTAL row style ────────────────────────────────────────
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8EAF6');
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ── Signature ─────────────────────────────────────────────
                $sigRow1  = $highestRow - 1;
                $sigRow2  = $highestRow;
                $sigStart = Coordinate::stringFromColumnIndex($highestColIndex - 2);
                $sigEnd   = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("{$sigStart}{$sigRow1}:{$sigEnd}{$sigRow1}");
                $s->getStyle("{$sigStart}{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$sigStart}{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("{$sigStart}{$sigRow2}:{$sigEnd}{$sigRow2}");
                $s->getStyle("{$sigStart}{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$sigStart}{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}