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
 * PAX synthesis sheet — one row per operator, qty + % columns.
 *
 * columns (4 cols):
 *   A  N°
 *   B  CIE
 *   C  PAX — Quantité       ← brut
 *   D  PAX — %              ← =IFERROR(C/C$total,0)
 *
 * Operators are sorted by grand total (sum of all metrics) descending.
 */
class VTAPAXSynthSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    /** @var array<string, array{traffic:int,idef:int}> */
    protected array $paxDep;
    protected array $sigles;

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $paxDep,
    ) {
        $this->sheetTitle      = $sheetTitle;
        $this->title           = $title;
        $this->paxDep          = $paxDep;

        // Build sigle list ordered by grand total (all metrics) descending
        $all = array_unique(array_merge(
            array_keys($paxDep),
        ));

        usort($all, function (string $a, string $b) use ($paxDep) {
            $total = (int) ($paxDep[$a]['traffic']  ?? 0);
            return $total;
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
        $cols = 4;
        $data = [];

        // Rows 1–3: document header
        foreach (
            [
                ['SERVICE VTA'],
                ["RVA AERO/N'DJILI"],
                ['DIVISION COMMERCIALE'],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }
        $data[] = array_fill(0, $cols, ''); // Row 4: spacer

        // Row 5: title
        $data[] = array_pad([$this->title], $cols, '');

        // Row 6: group labels  /  Row 7: Quantité–% sub-labels
        $data[] = [
            'N°',
            'CIE',
            'PAX',
            '',
        ];
        $data[] = ['', '', 'Quantité', '%'];

        // Data rows — raw qty only; % and formula cols left empty for AfterSheet
        foreach ($this->sigles as $sigle) {
            $pax = (int) ($this->paxDep[$sigle]['traffic']  ?? 0);
            $data[] = ['', $sigle, $pax, ''];
        }

        // TOTAL row
        $data[] = array_pad(['TOTAL'], $cols, '');

        // Signature
        $data[] = array_fill(0, $cols, '');
        $sig1              = array_fill(0, $cols, '');
        $sig1[$cols - 2]   = 'LE CHEF DE SERVICE VTA';
        $data[]            = $sig1;
        $sig2              = array_fill(0, $cols, '');
        $sig2[$cols - 2]   = 'MINSAY NKASER SAGESSE';
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
                $headerColor    = 'FF2E7D32';
                $subHeaderColor = 'FF43A047';
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

                $s->mergeCells("C{$headerRow1}:D{$headerRow1}"); // PAX
                // Blue accent on TOTAL GÉNÉRAL header cells
                $s->getStyle("C{$headerRow1}:D{$headerRow2}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($totalGenColor);

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

                $s->getColumnDimension("A")->setAutoSize(false)->setWidth(7);
                $s->getColumnDimension("B")->setAutoSize(false)->setWidth(15);
                $s->getColumnDimension("C")->setAutoSize(false)->setWidth(25); 
                $s->getColumnDimension("D")->setAutoSize(false)->setWidth(25);


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

                    // Force 0 on raw qty column — empty cells become 0
                    $v       = $s->getCell("C{$row}")->getValue();
                    $numeric = ($v === '' || $v === null) ? 0 : (int) $v;
                    $s->setCellValueExplicit("C{$row}", $numeric, DataType::TYPE_NUMERIC);
                    $s->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                        // % per metric pair
                        $s->getCell("D{$row}")->setValue("=IFERROR(C{$row}/C\${$totalsRow},0)");
                            $s->getStyle("D{$row}")->getNumberFormat()->setFormatCode('0.00%');
                            $s->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Alternating row background
                    if ($idx % 2 === 0) {
                        $s->getStyle("A{$row}:{$highestCol}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF9FBE7');
                    }
                }

                // TOTAL GÉNÉRAL on TOTAL row
                    $s->getCell("C{$totalsRow}")->setValue("=SUM(C{$firstDataRow}:C{$lastDataRow})");
                    $s->getStyle("C{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $s->getStyle("C{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("C{$totalsRow}")->getFont()->setBold(true);
                    $s->getCell("D{$totalsRow}")->setValue(1);
                    $s->getStyle("D{$totalsRow}")->getNumberFormat()->setFormatCode('0.00%');
                    $s->getStyle("D{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $s->getStyle("D{$totalsRow}")->getFont()->setBold(true);
                    $s->getStyle("C{$totalsRow}:D{$totalsRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFBBDEFB');

                // ── TOTAL row global style ─────────────────────────────────
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("A{$totalsRow}:{$highestCol}{$totalsRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
                $s->getRowDimension($totalsRow)->setRowHeight(18);

                // ── Signature ─────────────────────────────────────────────
                $sigRow1  = $highestRow - 1;
                $sigRow2  = $highestRow;
                $sigStart = Coordinate::stringFromColumnIndex($highestColIndex - 1);
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
