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
 * Feuille mensuelle Fret International.
 *
 * Colonnes :
 *   A  DATE
 *   B  TOTAL FRET DEBARQUE       ← brut
 *   C  TOTAL FRET EMBARQUE       ← brut
 *   D  TOTAL FRET IDEF DEBARQUE  ← brut
 *   E  TOTAL FRET IDEF EMBARQUE  ← brut
 *   F  ECART DEBARQUE            = B - D   ← formule Excel
 *   G  ECART EMBARQUE            = C - E   ← formule Excel
 *   H  PERCEPTION DEB            = D * 0.007 ← formule Excel
 *   I  PERCEPTION EMB            = E * 0.005 ← formule Excel
 *   J  TOTAL PERCEPTION          = H + I   ← formule Excel
 */
class InternationalFreightStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string  $sheetTitle;
    protected string  $title;
    protected string  $subTitle;
    protected array   $rows;
    protected array   $operators;
    protected string  $annexeNumber;

    public function __construct(
        string $sheetTitle,
        string $title,
        string $subTitle,
        array  $rows,
        array  $operators,
        string $annexeNumber
    ) {
        $this->rows         = $rows;
        $this->operators    = $operators;
        $this->sheetTitle   = $sheetTitle;
        $this->title        = $title;
        $this->subTitle     = $subTitle;
        $this->annexeNumber = $annexeNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    public function array(): array
    {
        $cols = 10;
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

        $data[] = ['DATE', 'TOTAL FRET', '', 'TOTAL FRET IDEF', '', 'ECART (EXONERE)', '', 'PERCEPTION ESTIMEE HORS DGDA', '', ''];
        $data[] = ['', 'DEBARQUE', 'EMBARQUE', 'DEBARQUE', 'EMBARQUE', 'DEBARQUE', 'EMBARQUE', 'F.DEB(0,007/kg)', 'F.EMB(0,005/kg)', 'TOTAL'];

        foreach ($this->rows as $dailyData) {
            [$arrTrafic, $depTrafic, $arrIdef, $depIdef] = $this->getRawFreightTotals($dailyData);
            $data[] = [
                $dailyData['DATE'] ?? '', // A
                $arrTrafic,              // B ← brut
                $depTrafic,              // C ← brut
                $arrIdef,                // D ← brut
                $depIdef,                // E ← brut
                '',                      // F ← formule B-D
                '',                      // G ← formule C-E
                '',                      // H ← formule D*0.007
                '',                      // I ← formule E*0.005
                '',                      // J ← formule H+I
            ];
        }

        $data[] = array_pad(['TOTAL'], $cols, '');
        $data[] = array_fill(0, $cols, '');

        $sig1               = array_fill(0, $cols, '');
        $sig1[$cols - 3]    = 'LE CHEF DE BUREAU IDEF';
        $data[]             = $sig1;

        $sig2               = array_fill(0, $cols, '');
        $sig2[$cols - 3]    = 'BANZE LUKUNGAY';
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

                // ── Formules par ligne ─────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    $s->getCell("F{$row}")->setValue("=B{$row}-D{$row}");   // ECART DEB
                    $s->getCell("G{$row}")->setValue("=C{$row}-E{$row}");   // ECART EMB
                    $s->getCell("H{$row}")->setValue("=D{$row}*0.007");     // PERCEPTION DEB
                    $s->getCell("I{$row}")->setValue("=E{$row}*0.005");     // PERCEPTION EMB
                    $s->getCell("J{$row}")->setValue("=H{$row}+I{$row}");   // TOTAL

                    foreach (['B', 'C', 'D', 'E'] as $col) {
                        $s->setCellValueExplicit("{$col}{$row}", $s->getCell("{$col}{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    }
                }

                // ── Ligne TOTAL ───────────────────────────────────────────
                $lastContent = $lastDataRow - 1;
                foreach (['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
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

                foreach ([6, 7] as $r) {
                    $s->mergeCells("A{$r}:{$highestCol}{$r}");
                    $s->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
                    $s->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$r}")->getFill()->setFillType('solid')
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

                $subRow = $headerRow + 1;
                $s->getStyle("A{$subRow}:{$highestCol}{$subRow}")
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$subRow}:{$highestCol}{$subRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Fusions groupes d'en-têtes
                $s->mergeCells("B{$headerRow}:C{$headerRow}");
                $s->mergeCells("D{$headerRow}:E{$headerRow}");
                $s->mergeCells("F{$headerRow}:G{$headerRow}");
                $s->mergeCells("H{$headerRow}:J{$headerRow}");

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

    private function getRawFreightTotals(array $dailyData): array
    {
        $depTrafic = 0;
        $depIdef = 0;
        $arrTrafic = 0;
        $arrIdef = 0;

        foreach ($dailyData as $key => $value) {
            if ($key === 'DATE') continue;
            if (isset($value['departure'])) {
                $depTrafic += $value['departure'];
                if ($key !== 'UN') $depIdef += $value['departure'];
            }
            if (isset($value['arrival'])) {
                $arrTrafic += $value['arrival'];
                if ($key !== 'UN') $arrIdef += $value['arrival'];
            }
        }
        return [$arrTrafic, $depTrafic, $arrIdef, $depIdef];
    }
}
