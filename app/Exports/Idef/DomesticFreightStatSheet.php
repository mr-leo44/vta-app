<?php

namespace App\Exports\Idef;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Feuille mensuelle Fret Domestique.
 *
 * Colonnes :
 *   A  DATE
 *   B  TOTAL FRET EMBARQUE        ← somme de tous les opérateurs (brut)
 *   C  TOTAL FRET IDEF EMBARQUE   ← somme des opérateurs hors UN (brut)
 *   D  ECART (EXONERE)            = B - C                       ← formule Excel
 *   E  PERCEPTION ESTIMEE         = C * 0.009                   ← formule Excel
 *
 * Ligne TOTAL : SUM pour B..E                                   ← formules Excel
 */
class DomesticFreightStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string  $sheetTitle;
    protected string  $title;
    protected string  $subTitle;
    protected array   $rows;
    protected array   $operators;
    protected ?string $annexeNumber;

    public function __construct(
        string  $sheetTitle,
        string  $title,
        string  $subTitle,
        array   $rows,
        array   $operators    = [],
        ?string $annexeNumber = null
    ) {
        $this->operators    = $operators;
        $this->sheetTitle   = $sheetTitle;
        $this->title        = $title;
        $this->subTitle     = $subTitle;
        $this->rows         = $rows;
        $this->annexeNumber = $annexeNumber;
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
                ['BUREAU IDEF'],
                ["RVA AERO/N'DJILI"],
                ["DIVISION COMMERCIALE"],
                ['', $this->annexeNumber],
                [$this->title],
                [$this->subTitle],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        $data[] = ['DATE', 'TOTAL FRET', 'TOTAL FRET IDEF', 'ECART (EXONERE)', 'PERCEPTION ESTIMEE HORS DGDA'];
        $data[] = ['', 'EMBARQUE', 'EMBARQUE', '', '(0,009/kg)'];

        foreach ($this->rows as $row) {
            [$trafficFret, $idefFret] = $this->getRawFreightTotals($row);
            $data[] = [
                $row['DATE'] ?? '',  // A
                $trafficFret,        // B ← brut
                $idefFret,           // C ← brut
                '',                  // D ← formule Excel
                '',                  // E ← formule Excel
            ];
        }

        $data[] = ['TOTAL', '', '', '', ''];

        $data[] = array_fill(0, $cols, '');

        $sig1               = array_fill(0, $cols, '');
        $sig1[$cols - 2]    = 'LE CHEF DE BUREAU IDEF';
        $data[]             = $sig1;

        $sig2               = array_fill(0, $cols, '');
        $sig2[$cols - 2]    = 'BANZE LUKUNGAY';
        $data[]             = $sig2;

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
                $headerRow       = 8;
                $firstDataRow    = $headerRow + 2;
                $lastDataRow     = $highestRow - 3;

                // ── Formules par ligne de données ─────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    // D = B - C  (écart / exonéré)
                    $s->getCell("D{$row}")->setValue("=B{$row}-C{$row}");
                    // E = C * 0.009  (perception estimée)
                    $s->getCell("E{$row}")->setValue("=C{$row}*0.009");

                    $s->setCellValueExplicit("B{$row}", $s->getCell("B{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("C{$row}", $s->getCell("C{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // ── Ligne TOTAL ───────────────────────────────────────────
                $lastContent = $lastDataRow - 1;
                foreach (['B', 'C', 'D', 'E'] as $col) {
                    $s->getCell("{$col}{$lastDataRow}")
                        ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastContent})");
                }

                // ── Styles ────────────────────────────────────────────────
                $this->applyCommonStyles($s, $highestRow, $highestCol, $highestColIndex, $headerRow, $firstDataRow, $lastDataRow);
            },
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retourne [trafficFret, idefFret] depuis une ligne de données.
     * trafficFret = somme de tous les opérateurs.
     * idefFret    = somme des opérateurs hors 'UN'.
     */
    private function getRawFreightTotals(array $row): array
    {
        $trafficFret = 0;
        $idefFret    = 0;
        array_shift($row); // Retirer DATE
        foreach ($row as $key => $value) {
            $trafficFret += (int) $value;
            if ($key !== 'UN') {
                $idefFret += (int) $value;
            }
        }
        return [$trafficFret, $idefFret];
    }

    private function applyCommonStyles($s, int $highestRow, string $highestCol, int $highestColIndex, int $headerRow, int $firstDataRow, int $lastDataRow): void
    {
        for ($row = 1; $row <= 4; $row++) {
            $s->mergeCells("A{$row}:{$highestCol}{$row}");
            $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
            $s->getStyle("A{$row}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        }

        foreach (['A6', 'A7'] as $cell) {
            $row = (int) filter_var($cell, FILTER_SANITIZE_NUMBER_INT);
            $s->mergeCells("{$cell}:{$highestCol}{$row}");
            $s->getStyle($cell)->getFont()->setBold(true)->setSize(16);
            $s->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $s->getStyle($cell)->getFill()->setFillType('solid')
                ->getStartColor()->setARGB('FFD9E1F2');
        }

        $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
            ->getFont()->setBold(true)->setSize(13);
        $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
            ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
        $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
            ->getFont()->getColor()->setARGB('FFFFFFFF');
        $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $subHeaderRow = $headerRow + 1;
        $s->getStyle("A{$subHeaderRow}:{$highestCol}{$subHeaderRow}")
            ->getFont()->setBold(true)->setSize(13);
        $s->getStyle("A{$subHeaderRow}:{$highestCol}{$subHeaderRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $s->getStyle("A{$headerRow}:{$highestCol}{$lastDataRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
            $s->getStyle("A{$row}:{$highestCol}{$row}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
            $s->getStyle("A{$row}:{$highestCol}{$row}")->getFont()->setSize(14);
        }

        for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
            $s->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
            ->getFont()->setBold(true)->setSize(16);
        $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        $s->getRowDimension($headerRow)->setRowHeight(25);
        for ($row = $headerRow; $row <= $lastDataRow; $row++) {
            $s->getRowDimension($row)->setRowHeight(24);
        }

        $sigRow1   = $highestRow - 1;
        $sigRow2   = $highestRow;
        $sigStart  = Coordinate::stringFromColumnIndex($highestColIndex - 1);
        $sigEnd    = Coordinate::stringFromColumnIndex($highestColIndex);

        $s->mergeCells("{$sigStart}{$sigRow1}:{$sigEnd}{$sigRow1}");
        $s->getStyle("{$sigStart}{$sigRow1}")->getFont()->setBold(true)->setSize(11);
        $s->getStyle("{$sigStart}{$sigRow1}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $s->mergeCells("{$sigStart}{$sigRow2}:{$sigEnd}{$sigRow2}");
        $s->getStyle("{$sigStart}{$sigRow2}")->getFont()->setBold(true)->setSize(12);
        $s->getStyle("{$sigStart}{$sigRow2}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }
}
