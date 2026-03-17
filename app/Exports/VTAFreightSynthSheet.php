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
 * Freight synthesis sheet — one row per operator, qty + % columns.
 *
 * Domestic columns (8 data cols):
 *   A  N° (order)
 *   B  CIE (operator sigle)
 *   C  Fret Départ — Quantité    ← brut
 *   D  Fret Départ — %           ← formula  =C{n}/C$total
 *   E  Excéd. Départ — Quantité  ← brut
 *   F  Excéd. Départ — %         ← formula  =E{n}/E$total
 *   G  TOTAL Fret+Excéd          ← formula  =C{n}+E{n}
 *   H  TOTAL %                   ← formula  =G{n}/G$total
 *
 * International adds:
 *   … same columns plus Fret Arrivée and Excéd. Arrivée pairs …
 *
 * The sheet receives the commercial OR non_commercial operator map directly.
 * A separate instance is instantiated for each group.
 */
class VTAFreightSynthSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array  $fretDep;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array  $excedDep;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array  $fretArr;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array  $excedArr;
    protected bool   $isInternational;
    protected array  $sigles; // Ordered list of operator sigles

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $fretDep,
        array  $excedDep,
        bool   $isInternational = false,
        array  $fretArr         = [],
        array  $excedArr        = []
    ) {
        $this->sheetTitle      = $sheetTitle;
        $this->title           = $title;
        $this->fretDep         = $fretDep;
        $this->excedDep        = $excedDep;
        $this->fretArr         = $fretArr;
        $this->excedArr        = $excedArr;
        $this->isInternational = $isInternational;

        // Build ordered sigle list (union of all groups)
        $all = array_unique(array_merge(
            array_keys($fretDep),
            array_keys($excedDep),
            array_keys($fretArr),
            array_keys($excedArr)
        ));
        sort($all);
        $this->sigles = $all;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function array(): array
    {
        $cols = $this->isInternational ? 10 : 8;
        $data = [];

        // Header rows 1-3
        foreach ([
            ['SERVICE VTA'],
            ["RVA AERO/N'DJILI"],
            ['DIVISION COMMERCIALE'],
        ] as $line) {
            $data[] = array_pad($line, $cols, '');
        }

        // Title (row 5)
        $data[] = array_pad([$this->title], $cols, '');

        // Group header (row 6)
        if ($this->isInternational) {
            $data[] = ['N°', 'CIE', 'FRET DÉPART', '', 'FRET ARRIVÉE', '', 'EXCÉD. DÉPART', '', 'EXCÉD. ARRIVÉE', ''];
            $data[] = ['', '', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%'];
        } else {
            $data[] = ['N°', 'CIE', 'FRET DÉPART', '', 'EXCÉD. DÉPART', '', 'TOTAL', ''];
            $data[] = ['', '', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%'];
        }

        // Data rows — raw brut values, % and totals filled by AfterSheet
        foreach ($this->sigles as $sigle) {
            if ($this->isInternational) {
                $data[] = [
                    '',           // A: N° (filled in AfterSheet)
                    $sigle,       // B: CIE
                    (int) ($this->fretDep[$sigle]['traffic']  ?? 0), // C
                    '',           // D: % fret dep
                    (int) ($this->fretArr[$sigle]['traffic']  ?? 0), // E
                    '',           // F: % fret arr
                    (int) ($this->excedDep[$sigle]['traffic'] ?? 0), // G
                    '',           // H: % exced dep
                    (int) ($this->excedArr[$sigle]['traffic'] ?? 0), // I
                    '',           // J: % exced arr
                ];
            } else {
                $data[] = [
                    '',           // A: N°
                    $sigle,       // B: CIE
                    (int) ($this->fretDep[$sigle]['traffic']  ?? 0), // C
                    '',           // D: % fret dep
                    (int) ($this->excedDep[$sigle]['traffic'] ?? 0), // E
                    '',           // F: % exced dep
                    '',           // G: TOTAL = formula
                    '',           // H: TOTAL % = formula
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

                $headerRow1   = 6;
                $headerRow2   = 7;
                $firstDataRow = 8;
                $opCount      = count($this->sigles);
                $lastDataRow  = $firstDataRow + $opCount - 1;
                $totalsRow    = $lastDataRow + 1;

                // ── Document header ───────────────────────────────────────
                for ($r = 1; $r <= 3; $r++) {
                    $s->mergeCells("A{$r}:{$highestCol}{$r}");
                    $s->getStyle("A{$r}")->getFont()->setBold(false)->setSize(11);
                    $s->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // ── Title ─────────────────────────────────────────────────
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(13);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD9E1F2');
                $s->getRowDimension(5)->setRowHeight(22);

                // ── Group header merges ───────────────────────────────────
                $headerColor    = $this->isInternational ? 'FF1B5E20' : 'FF2E7D32';
                $subHeaderColor = $this->isInternational ? 'FF388E3C' : 'FF43A047';

                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($headerColor);
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow1}:{$highestCol}{$headerRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Merge N° and CIE across both header rows
                $s->mergeCells("A{$headerRow1}:A{$headerRow2}");
                $s->mergeCells("B{$headerRow1}:B{$headerRow2}");

                if ($this->isInternational) {
                    $s->mergeCells("C{$headerRow1}:D{$headerRow1}"); // FRET DEP
                    $s->mergeCells("E{$headerRow1}:F{$headerRow1}"); // FRET ARR
                    $s->mergeCells("G{$headerRow1}:H{$headerRow1}"); // EXCED DEP
                    $s->mergeCells("I{$headerRow1}:J{$headerRow1}"); // EXCED ARR
                } else {
                    $s->mergeCells("C{$headerRow1}:D{$headerRow1}"); // FRET DEP
                    $s->mergeCells("E{$headerRow1}:F{$headerRow1}"); // EXCED DEP
                    $s->mergeCells("G{$headerRow1}:H{$headerRow1}"); // TOTAL
                }

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

                // ── Borders ───────────────────────────────────────────────
                $s->getStyle("A{$headerRow1}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Data rows ─────────────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $idx = $row - $firstDataRow;

                    // Row N° (column A)
                    $s->setCellValueExplicit("A{$row}", $idx + 1, DataType::TYPE_NUMERIC);
                    $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // CIE column B: left-aligned, bold
                    $s->getStyle("B{$row}")->getFont()->setBold(true);
                    $s->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Force numeric on qty columns, right-align all numeric cols
                    $qtyCols = $this->isInternational ? ['C', 'E', 'G', 'I'] : ['C', 'E'];
                    foreach ($qtyCols as $cl) {
                        $v = $s->getCell("{$cl}{$row}")->getValue();
                        $s->setCellValueExplicit("{$cl}{$row}", (int) $v, DataType::TYPE_NUMERIC);
                        $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('#,##0');
                        $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // % formulas — reference the TOTAL row with absolute row ($totalsRow)
                    if ($this->isInternational) {
                        // D = C / C$total (fret dep %)
                        $s->getCell("D{$row}")->setValue("=IFERROR(C{$row}/C\${$totalsRow},0)");
                        // F = E / E$total (fret arr %)
                        $s->getCell("F{$row}")->setValue("=IFERROR(E{$row}/E\${$totalsRow},0)");
                        // H = G / G$total (exced dep %)
                        $s->getCell("H{$row}")->setValue("=IFERROR(G{$row}/G\${$totalsRow},0)");
                        // J = I / I$total (exced arr %)
                        $s->getCell("J{$row}")->setValue("=IFERROR(I{$row}/I\${$totalsRow},0)");

                        foreach (['D', 'F', 'H', 'J'] as $cl) {
                            $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('0.00%');
                            $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                    } else {
                        // D = C / C$total (fret dep %)
                        $s->getCell("D{$row}")->setValue("=IFERROR(C{$row}/C\${$totalsRow},0)");
                        // F = E / E$total (exced dep %)
                        $s->getCell("F{$row}")->setValue("=IFERROR(E{$row}/E\${$totalsRow},0)");
                        // G = C + E (total qty)
                        $s->getCell("G{$row}")->setValue("=C{$row}+E{$row}");
                        // H = G / G$total (total %)
                        $s->getCell("H{$row}")->setValue("=IFERROR(G{$row}/G\${$totalsRow},0)");

                        foreach (['D', 'F', 'H'] as $cl) {
                            $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('0.00%');
                            $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                        $s->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
                        $s->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Alternating rows
                    if ($idx % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF9FBE7');
                    }
                }

                // ── TOTAL row: SUM on qty cols only ───────────────────────
                $qtyCols = $this->isInternational ? ['C', 'E', 'G', 'I'] : ['C', 'E', 'G'];
                foreach ($qtyCols as $cl) {
                    $s->getCell("{$cl}{$totalsRow}")
                      ->setValue("=SUM({$cl}{$firstDataRow}:{$cl}{$lastDataRow})");
                    $s->getStyle("{$cl}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("{$cl}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                // % cols on total row = 100% by definition
                $pctCols = $this->isInternational ? ['D', 'F', 'H', 'J'] : ['D', 'F', 'H'];
                foreach ($pctCols as $cl) {
                    $s->getCell("{$cl}{$totalsRow}")->setValue(1); // 1 = 100%
                    $s->getStyle("{$cl}{$totalsRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $s->getStyle("{$cl}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8F5E9');
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ── Signature ─────────────────────────────────────────────
                $sigRow1  = $highestRow - 1;
                $sigRow2  = $highestRow;
                $sigStart = Coordinate::stringFromColumnIndex($highestColIndex - 2);
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
}