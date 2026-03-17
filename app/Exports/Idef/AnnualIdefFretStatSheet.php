<?php

namespace App\Exports\Idef;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Feuille annuelle IDEF Fret (récapitulatif mensuel).
 *
 * Colonnes :
 *   A  MOIS
 *   B  POIDS 1/0,009          = CEILING(C / 0.009, 1)       ← formule Excel
 *   C  USD
 *   D  CDF
 *   E  VALEUR EN $ tx/Mois    = CEILING(D / rate_du_mois, 1) ← formule Excel
 *   F  POIDS 2/0,009          = CEILING(E / 0.009, 1)        ← formule Excel
 *   G  TOTAL POIDS            = B + F                        ← formule Excel
 *
 * Le taux mensuel est injecté colonne H (masquée / hors-tableau) afin que
 * chaque ligne puisse s'y référer dynamiquement via =CEILING(D{n}/H{n},1).
 * Cela permet de modifier le taux directement dans la cellule H pour recalculer.
 */
class AnnualIdefFretStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $rows;
    protected string $annexeNumber;

    public function __construct(string $sheetTitle, string $title, array $rows, string $annexeNumber)
    {
        $this->sheetTitle   = $sheetTitle;
        $this->title        = $title;
        $this->rows         = $rows;
        $this->annexeNumber = $annexeNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // array() : données brutes uniquement (MOIS, USD, CDF, taux)
    // ─────────────────────────────────────────────────────────────────────────
    public function array(): array
    {
        $cols = 8; // A..G + H (taux caché)
        $data = [];

        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU IDEF'],
                ["RVA AERO/N'DJILI"],
                ["DIVISION COMMERCIALE"],
                ['', $this->annexeNumber],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        // En-têtes (3 lignes, fusionnées dans AfterSheet)
        $data[] = ['MOIS', 'POIDS 1/0,009', 'USD', 'MONTANT EN CAISSE', '', '', 'TOTAL POIDS', 'TAUX'];
        $data[] = ['', '', '', 'CDF', '', '', '', ''];
        $data[] = ['', '', '', 'CDF', 'VALEUR EN $ tx/Mois', 'POIDS 2/0,009', '', ''];

        // Données brutes : MOIS, USD, CDF, taux (col H)
        foreach ($this->rows['idef_fret'] as $row) {
            $data[] = [
                $this->getMonthName($row['MOIS']), // A
                '',                                // B ← formule
                $row['usd'],                       // C ← brut
                $row['cdf'],                       // D ← brut
                '',                                // E ← formule
                '',                                // F ← formule
                '',                                // G ← formule
                $row['rate'],                      // H ← taux du mois (utilisé par formule E)
            ];
        }

        $data[] = array_pad(['TOTAL'], $cols, '');

        $data[] = array_fill(0, $cols, '');

        $sig1    = array_fill(0, $cols, '');
        $sig1[0] = 'CB RECETTE:  KIBANZA';
        $sig1[4] = 'LE CHEF DE BUREAU IDEF';
        $data[]  = $sig1;

        $sig2    = array_fill(0, $cols, '');
        $sig2[0] = 'CB BANQUE : LOMPOKO';
        $sig2[4] = 'BANZE LUKUNGAY';
        $data[]  = $sig2;

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // registerEvents() : formules + style
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
                $headerRow       = 7;
                $firstDataRow    = $headerRow + 3;
                $lastDataRow     = $highestRow - 3; // ligne TOTAL

                // ── Formules par ligne ────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    // B = CEILING(C / 0.009, 1)
                    $s->getCell("B{$row}")->setValue("=IFERROR(CEILING(C{$row}/0.009,1),0)");
                    // E = CEILING(D / H[taux], 1)  — H contient le taux du mois
                    $s->getCell("E{$row}")->setValue("=IFERROR(CEILING(D{$row}/H{$row},1),0)");
                    // F = CEILING(E / 0.009, 1)
                    $s->getCell("F{$row}")->setValue("=IFERROR(CEILING(E{$row}/0.009,1),0)");
                    // G = B + F
                    $s->getCell("G{$row}")->setValue("=B{$row}+F{$row}");

                    // Forcer numérique sur données brutes
                    $s->setCellValueExplicit("C{$row}", $s->getCell("C{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("D{$row}", $s->getCell("D{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("H{$row}", $s->getCell("H{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // ── Ligne TOTAL ───────────────────────────────────────────
                $lastContent = $lastDataRow - 1;
                foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                    $s->getCell("{$col}{$lastDataRow}")
                        ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastContent})");
                }

                // ── Masquer colonne H (taux) ──────────────────────────────
                $s->getColumnDimension('H')->setVisible(false);

                // ── Style titres ──────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:G{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);
                $s->mergeCells("A6:G6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // ── Style en-têtes (3 lignes) ─────────────────────────────
                $s->getStyle("A{$headerRow}:G" . ($headerRow + 2))
                    ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:G" . ($headerRow + 2))
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:G" . ($headerRow + 2))
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$headerRow}:G" . ($headerRow + 2))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                foreach (['A', 'B', 'C', 'G'] as $col) {
                    $s->mergeCells("{$col}{$headerRow}:{$col}" . ($headerRow + 2));
                }
                $s->mergeCells("D{$headerRow}:F{$headerRow}");
                $s->mergeCells("D" . ($headerRow + 1) . ":F" . ($headerRow + 1));

                // ── Bordures ──────────────────────────────────────────────
                $s->getStyle("A{$headerRow}:G{$lastDataRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Style données ─────────────────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:G{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$row}:G{$row}")->getFont()->setSize(14);
                }

                // Mois : aligné à gauche
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // ── Style TOTAL ───────────────────────────────────────────
                $s->getStyle("A{$lastDataRow}:G{$lastDataRow}")
                    ->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$lastDataRow}:G{$lastDataRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$lastDataRow}:G{$lastDataRow}")
                    ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                // ── Hauteurs ──────────────────────────────────────────────
                $s->getRowDimension($headerRow)->setRowHeight(25);
                for ($row = $headerRow; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(24);
                }

                // ── Signature ─────────────────────────────────────────────
                $sigRow1  = $highestRow - 1;
                $sigRow2  = $highestRow;

                $s->mergeCells("A{$sigRow1}:B{$sigRow1}");
                $s->mergeCells("E{$sigRow1}:G{$sigRow1}");
                $s->getStyle("E{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("E{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("A{$sigRow2}:B{$sigRow2}");
                $s->mergeCells("E{$sigRow2}:G{$sigRow2}");
                $s->getStyle("E{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("E{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    private function getMonthName(string $row): string
    {
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
        return $monthNames[explode('-', $row)[0]] ?? $row;
    }
}
