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
 * Feuille mensuelle Écart PAX / Go-Pass.
 *
 * Colonnes :
 *   A  DATE
 *   B  NOMBRE PAX EMBARQUES      ← brut
 *   C  NOMBRE GO-PASS RAMASSES   ← brut
 *   D  ECART                     = B - C   ← formule Excel
 *   E  JUSTIFICATIONS            ← texte brut
 *
 * Ligne TOTAL :
 *   B  =SUM(...)
 *   C  =SUM(...)
 *   D  =SUM(...)   (ou =B{total}-C{total} — cohérent dans les deux cas)
 */
class EcartStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $rows;

    public function __construct(string $sheetTitle, string $title, array $rows)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title      = $title;
        $this->rows       = $rows;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
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
                ['', 'SYNTHESE'],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        $data[] = ['DATE', 'NOMBRE PAX EMBARQUES', 'NOMBRE DES GO-PASS RAMASSES', 'ECART', 'JUSTIFICATIONS'];

        foreach ($this->rows as $row) {
            $date       = $row['DATE'] ?? '';
            $totalPax   = 0;
            $totalGopass = 0;
            $rowCopy    = $row;
            array_shift($rowCopy);
            foreach ($rowCopy as $value) {
                $totalPax    += $value['trafic'];
                $totalGopass += $value['gopass'];
            }
            $data[] = [
                $date,                                  // A
                $totalPax,                              // B ← brut
                $totalGopass,                           // C ← brut
                '',                                     // D ← formule Excel B-C
                $this->getDayJustifications($rowCopy),  // E ← texte
            ];
        }

        $data[] = ['TOTAL', '', '', '', $this->getMonthJustifications()];

        $data[] = array_fill(0, $cols, '');

        $sig1            = array_fill(0, $cols, '');
        $sig1[$cols - 1] = 'LE CHEF DE BUREAU IDEF';
        $data[]          = $sig1;

        $sig2            = array_fill(0, $cols, '');
        $sig2[$cols - 1] = 'BANZE LUKUNGAY';
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
                $s->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);

                $highestRow      = $s->getHighestRow();
                $highestCol      = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);
                $headerRow       = 7;
                $firstDataRow    = $headerRow + 1;
                $lastDataRow     = $highestRow - 3;

                // ── Formule ECART par ligne ───────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    $s->getCell("D{$row}")->setValue("=B{$row}-C{$row}");

                    $s->setCellValueExplicit("B{$row}", $s->getCell("B{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("C{$row}", $s->getCell("C{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // ── Ligne TOTAL ───────────────────────────────────────────
                $lastContent = $lastDataRow - 1;
                foreach (['B', 'C', 'D'] as $col) {
                    $s->getCell("{$col}{$lastDataRow}")
                        ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastContent})");
                }

                // ── Styles ────────────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }
                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);
                $s->getStyle("B5")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $s->getStyle("A{$headerRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:D{$row}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$row}:D{$row}")->getFont()->setSize(14);
                    $s->getStyle("E{$row}")->getFont()->setSize(14);
                    $s->getStyle("E{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }

                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                $s->getRowDimension($headerRow)->setRowHeight(25);
                for ($row = $headerRow + 1; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(24);
                }

                $sigRow1 = $highestRow - 1;
                $sigRow2 = $highestRow;
                $sigEnd  = Coordinate::stringFromColumnIndex($highestColIndex);
                $s->getStyle("{$sigEnd}{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$sigEnd}{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("{$sigEnd}{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$sigEnd}{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    // ─── Justifications helpers (inchangés — texte libre) ────────────────────

    private function getDayJustifications(array $row): string
    {
        $sum = [];
        foreach ($row as $value) {
            if (empty($value['justifications'])) continue;
            foreach ($value['justifications'] as $key => $just) {
                if ($key === 'Militaires') {
                    $sum[$key] ??= ['sfr' => 0, 'value' => 0];
                    $sum[$key]['sfr']   += $just['sfr'];
                    $sum[$key]['value'] += $just['value'];
                } else {
                    $sum[$key] = ($sum[$key] ?? 0) + $just;
                }
            }
        }
        return $this->formatJustifications($sum);
    }

    private function getMonthJustifications(): string
    {
        $sum = [];
        foreach ($this->rows as $dayRow) {
            $copy = $dayRow;
            array_shift($copy);
            foreach ($copy as $value) {
                if (empty($value['justifications'])) continue;
                foreach ($value['justifications'] as $key => $just) {
                    if ($key === 'Militaires') {
                        $sum[$key] ??= ['sfr' => 0, 'value' => 0];
                        $sum[$key]['sfr']   += $just['sfr'];
                        $sum[$key]['value'] += $just['value'];
                    } else {
                        $sum[$key] = ($sum[$key] ?? 0) + $just;
                    }
                }
            }
        }
        return $this->formatJustifications($sum);
    }

    private function formatJustifications(array $sum): string
    {
        if (empty($sum)) return 'RAS';
        $parts = [];
        foreach ($sum as $key => $value) {
            if ($key === 'Militaires') {
                $label  = $value['value'] > 1 ? 'Militaires' : 'Militaire';
                $parts[] = $value['sfr'] > 0
                    ? "{$value['value']} {$label} ({$value['sfr']} sfr)"
                    : "{$value['value']} {$label}";
            } else {
                $parts[] = "{$value} {$key}";
            }
        }
        return implode(', ', $parts);
    }
}
