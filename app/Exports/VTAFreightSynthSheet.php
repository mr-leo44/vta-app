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
 * Domestic columns (10 cols):
 *   A  N°
 *   B  CIE
 *   C  Fret Départ — Quantité       ← brut
 *   D  Fret Départ — %              ← =IFERROR(C/C$total,0)
 *   E  Excéd. Départ — Quantité     ← brut
 *   F  Excéd. Départ — %            ← =IFERROR(E/E$total,0)
 *   G  TOTAL FRET+EXCÉD — Quantité  ← =C+E
 *   H  TOTAL FRET+EXCÉD — %         ← =IFERROR(G/G$total,0)
 *   I  TOTAL GÉNÉRAL — Quantité     ← =G  (domestic: no arrivals, same as G)
 *   J  TOTAL GÉNÉRAL — %            ← =IFERROR(I/I$total,0)
 *
 * International columns (12 cols):
 *   A  N°
 *   B  CIE
 *   C  Fret Départ — Quantité       ← brut
 *   D  Fret Départ — %
 *   E  Fret Arrivée — Quantité      ← brut
 *   F  Fret Arrivée — %
 *   G  Excéd. Départ — Quantité     ← brut
 *   H  Excéd. Départ — %
 *   I  Excéd. Arrivée — Quantité    ← brut
 *   J  Excéd. Arrivée — %
 *   K  TOTAL GÉNÉRAL — Quantité     ← =C+E+G+I
 *   L  TOTAL GÉNÉRAL — %            ← =IFERROR(K/K$total,0)
 *
 * Operators are sorted by grand total (sum of all metrics) descending.
 */
class VTAFreightSynthSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array $fretDep;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array $excedDep;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array $fretArr;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array $excedArr;
    protected bool  $isInternational;
    protected array $sigles;

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

        // Build sigle list ordered by grand total (all metrics) descending
        $all = array_unique(array_merge(
            array_keys($fretDep),
            array_keys($excedDep),
            array_keys($fretArr),
            array_keys($excedArr)
        ));
        usort($all, function (string $a, string $b) use ($fretDep, $excedDep, $fretArr, $excedArr) {
            $totalA = (int) ($fretDep[$a]['traffic']  ?? 0)
                    + (int) ($excedDep[$a]['traffic'] ?? 0)
                    + (int) ($fretArr[$a]['traffic']  ?? 0)
                    + (int) ($excedArr[$a]['traffic'] ?? 0);
            $totalB = (int) ($fretDep[$b]['traffic']  ?? 0)
                    + (int) ($excedDep[$b]['traffic'] ?? 0)
                    + (int) ($fretArr[$b]['traffic']  ?? 0)
                    + (int) ($excedArr[$b]['traffic'] ?? 0);
            return $totalB <=> $totalA;
        });
        $this->sigles = $all;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // array() — raw qty values injected; formulas + styles applied in AfterSheet
    // ─────────────────────────────────────────────────────────────────────────
    public function array(): array
    {
        $cols = $this->isInternational ? 12 : 8;
        $data = [];

        // Rows 1–3: document header
        foreach ([
            ['SERVICE VTA'],
            ["RVA AERO/N'DJILI"],
            ['DIVISION COMMERCIALE'],
        ] as $line) {
            $data[] = array_pad($line, $cols, '');
        }
        $data[] = array_fill(0, $cols, ''); // Row 4: spacer

        // Row 5: title
        $data[] = array_pad([$this->title], $cols, '');

        // Row 6: group labels  /  Row 7: Quantité–% sub-labels
        if ($this->isInternational) {
            $data[] = [
                'N°', 'CIE',
                'FRET DÉPART',    '', 'FRET ARRIVÉE',   '',
                'EXCÉD. DÉPART',  '', 'EXCÉD. ARRIVÉE', '',
                'TOTAL GÉNÉRAL',  '',
            ];
            $data[] = ['', '', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%'];
        } else {
            $data[] = [
                'N°', 'CIE',
                'FRET DÉPART', '', 'EXCÉD. DÉPART', '',
                'TOTAL FRET+EXCÉD', '',
            ];
            $data[] = ['', '', 'Quantité', '%', 'Quantité', '%', 'Quantité', '%'];
        }

        // Data rows — raw qty only; % and formula cols left empty for AfterSheet
        foreach ($this->sigles as $sigle) {
            $fd = (int) ($this->fretDep[$sigle]['traffic']  ?? 0);
            $fa = (int) ($this->fretArr[$sigle]['traffic']  ?? 0);
            $ed = (int) ($this->excedDep[$sigle]['traffic'] ?? 0);
            $ea = (int) ($this->excedArr[$sigle]['traffic'] ?? 0);

            if ($this->isInternational) {
                $data[] = ['', $sigle, $fd, '', $fa, '', $ed, '', $ea, '', '', ''];
            } else {
                $data[] = ['', $sigle, $fd, '', $ed, '', '', ''];
            }
        }

        // TOTAL row
        $data[] = array_pad(['TOTAL'], $cols, '');

        // Signature
        $data[] = array_fill(0, $cols, '');
        $sig1              = array_fill(0, $cols, '');
        $sig1[$cols - 3]   = 'LE CHEF DE SERVICE VTA';
        $data[]            = $sig1;
        $sig2              = array_fill(0, $cols, '');
        $sig2[$cols - 3]   = 'MINSAY NKASER SAGESSE';
        $data[]            = $sig2;

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

                $headerRow1   = 6;
                $headerRow2   = 7;
                $firstDataRow = 8;
                $opCount      = count($this->sigles);
                $lastDataRow  = $firstDataRow + $opCount - 1;
                $totalsRow    = $lastDataRow + 1;

                // ── Document header rows 1–3 ──────────────────────────────
                for ($r = 1; $r <= 3; $r++) {
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
                $headerColor    = $this->isInternational ? 'FF1B5E20' : 'FF2E7D32';
                $subHeaderColor = $this->isInternational ? 'FF388E3C' : 'FF43A047';
                $totalGenColor  = 'FF1565C0'; // blue accent for TOTAL GÉNÉRAL

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
                    $s->mergeCells("K{$headerRow1}:L{$headerRow1}"); // TOTAL GÉNÉRAL
                    // Blue accent on TOTAL GÉNÉRAL header cells
                    $s->getStyle("K{$headerRow1}:L{$headerRow2}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($totalGenColor);
                } else {
                    $s->mergeCells("C{$headerRow1}:D{$headerRow1}"); // FRET DEP
                    $s->mergeCells("E{$headerRow1}:F{$headerRow1}"); // EXCED DEP
                    $s->mergeCells("G{$headerRow1}:H{$headerRow1}"); // TOTAL FRET+EXCÉD
                    // Blue accent on TOTAL GÉNÉRAL header cells
                    $s->getStyle("G{$headerRow1}:H{$headerRow2}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($totalGenColor);
                }

                // ── Sub-header row 7 ──────────────────────────────────────
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

                // ── Borders on full table ─────────────────────────────────
                $s->getStyle("A{$headerRow1}:{$highestCol}{$totalsRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Data rows ─────────────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $idx = $row - $firstDataRow;

                    // A: N° (sequential)
                    $s->setCellValueExplicit("A{$row}", $idx + 1, DataType::TYPE_NUMERIC);
                    $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // B: CIE
                    $s->getStyle("B{$row}")->getFont()->setBold(true);
                    $s->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    // Force 0 on raw qty columns — empty cells become 0
                    $rawQtyCols = $this->isInternational ? ['C', 'E', 'G', 'I'] : ['C', 'E'];
                    foreach ($rawQtyCols as $cl) {
                        $v       = $s->getCell("{$cl}{$row}")->getValue();
                        $numeric = ($v === '' || $v === null) ? 0 : (int) $v;
                        $s->setCellValueExplicit("{$cl}{$row}", $numeric, DataType::TYPE_NUMERIC);
                        $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('#,##0');
                        $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    if ($this->isInternational) {
                        // % per metric pair
                        $s->getCell("D{$row}")->setValue("=IFERROR(C{$row}/C\${$totalsRow},0)");
                        $s->getCell("F{$row}")->setValue("=IFERROR(E{$row}/E\${$totalsRow},0)");
                        $s->getCell("H{$row}")->setValue("=IFERROR(G{$row}/G\${$totalsRow},0)");
                        $s->getCell("J{$row}")->setValue("=IFERROR(I{$row}/I\${$totalsRow},0)");
                        foreach (['D', 'F', 'H', 'J'] as $cl) {
                            $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('0.00%');
                            $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                        // K: TOTAL GÉNÉRAL qty
                        $s->getCell("K{$row}")->setValue("=C{$row}+E{$row}+G{$row}+I{$row}");
                        $s->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                        $s->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("K{$row}")->getFont()->setBold(true);
                        // L: TOTAL GÉNÉRAL %
                        $s->getCell("L{$row}")->setValue("=IFERROR(K{$row}/K\${$totalsRow},0)");
                        $s->getStyle("L{$row}")->getNumberFormat()->setFormatCode('0.00%');
                        $s->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $s->getStyle("L{$row}")->getFont()->setBold(true);
                        // Blue tint on TOTAL GÉNÉRAL data cells
                        $s->getStyle("K{$row}:L{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE3F2FD');
                    } else {
                        // D: fret dep %
                        $s->getCell("D{$row}")->setValue("=IFERROR(C{$row}/C\${$totalsRow},0)");
                        // F: exced dep %
                        $s->getCell("F{$row}")->setValue("=IFERROR(E{$row}/E\${$totalsRow},0)");
                        foreach (['D', 'F'] as $cl) {
                            $s->getStyle("{$cl}{$row}")->getNumberFormat()->setFormatCode('0.00%');
                            $s->getStyle("{$cl}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                        // G: TOTAL FRET+EXCÉD qty
                        $s->getCell("G{$row}")->setValue("=C{$row}+E{$row}");
                        $s->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0');
                        $s->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        // H: TOTAL FRET+EXCÉD %
                        $s->getCell("H{$row}")->setValue("=IFERROR(G{$row}/G\${$totalsRow},0)");
                        $s->getStyle("H{$row}")->getNumberFormat()->setFormatCode('0.00%');
                        $s->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Alternating row background
                    if ($idx % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FBE7');
                    }
                }

                // ── TOTAL row: SUM on raw qty cols ────────────────────────
                $rawQtyCols = $this->isInternational ? ['C', 'E', 'G', 'I'] : ['C', 'E'];
                foreach ($rawQtyCols as $cl) {
                    $s->getCell("{$cl}{$totalsRow}")
                        ->setValue("=SUM({$cl}{$firstDataRow}:{$cl}{$lastDataRow})");
                    $s->getStyle("{$cl}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("{$cl}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                // % cols on TOTAL row = 100%
                $pctCols = $this->isInternational ? ['D', 'F', 'H', 'J'] : ['D', 'F', 'H'];
                foreach ($pctCols as $cl) {
                    $s->getCell("{$cl}{$totalsRow}")->setValue(1);
                    $s->getStyle("{$cl}{$totalsRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $s->getStyle("{$cl}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // TOTAL GÉNÉRAL on TOTAL row
                if ($this->isInternational) {
                    $s->getCell("K{$totalsRow}")->setValue("=SUM(K{$firstDataRow}:K{$lastDataRow})");
                    $s->getStyle("K{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("K{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("K{$totalsRow}")->getFont()->setBold(true);
                    $s->getCell("L{$totalsRow}")->setValue(1);
                    $s->getStyle("L{$totalsRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $s->getStyle("L{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("L{$totalsRow}")->getFont()->setBold(true);
                    $s->getStyle("K{$totalsRow}:L{$totalsRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFBBDEFB');
                } else {
                    $s->getCell("G{$totalsRow}")->setValue("=SUM(G{$firstDataRow}:G{$lastDataRow})");
                    $s->getStyle("G{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("G{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getCell("H{$totalsRow}")->setValue(1);
                    $s->getStyle("H{$totalsRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $s->getStyle("H{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ── TOTAL row global style ─────────────────────────────────
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
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