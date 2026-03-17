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
 * Feuille mensuelle IDEF Fret.
 *
 * Colonnes :
 *   A  DATE
 *   B  POIDS 1/0,009          = CEIL(C / 0.009)            ← formule Excel
 *   C  USD
 *   D  CDF (montant en caisse)
 *   E  VALEUR EN $ tx/Mois    = CEIL(D / monthly_rate)     ← formule Excel
 *   F  POIDS 2/0,009          = E / 0.009                  ← formule Excel
 *   G  TOTAL POIDS            = B + F                      ← formule Excel
 *
 * Ligne TOTAL :
 *   B..G  = SUM(Bfirst:Blast)                              ← formule Excel
 *
 * Seules les données brutes (DATE, USD, CDF) sont injectées comme valeurs.
 * Tout le reste est calculé dynamiquement par Excel.
 */
class IdefFretStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
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
    // array() : uniquement les données brutes + libellés fixes
    // ─────────────────────────────────────────────────────────────────────────
    public function array(): array
    {
        $cols = 7;
        $data = [];

        // En-têtes du document
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

        // En-têtes de colonnes (3 lignes fusionnées dans AfterSheet)
        $data[] = ['DATE', 'POIDS 1/0,009', 'USD', 'MONTANT EN CAISSE', '', '', 'TOTAL POIDS'];
        $data[] = ['', '', '', 'CDF', '', '', ''];
        $data[] = ['', '', '', 'CDF', "VALEUR EN $ tx/{$this->rows['monthly_rate']}", 'POIDS 2/0,009', ''];

        // Données brutes : DATE, USD, CDF uniquement — le reste sera calculé par Excel
        foreach ($this->rows['idef_fret'] as $row) {
            $data[] = [
                $row['DATE'],   // A
                '',             // B  ← formule posée dans AfterSheet
                $row['usd'],    // C  ← donnée brute
                $row['cdf'],    // D  ← donnée brute
                '',             // E  ← formule posée dans AfterSheet
                '',             // F  ← formule posée dans AfterSheet
                '',             // G  ← formule posée dans AfterSheet
            ];
        }

        $data[] = ['TOTAL', '', '', '', '', '', '']; // Ligne totaux (formules dans AfterSheet)

        // Lignes vides + signatures
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
    // registerEvents() : style + toutes les formules Excel
    // ─────────────────────────────────────────────────────────────────────────
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // ── Page ──────────────────────────────────────────────────
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0)
                    ->setHorizontalCentered(true);
                $s->getPageMargins()->setTop(0.25)->setBottom(0.25)->setLeft(0.25)->setRight(0.25);

                // ── Dimensions ────────────────────────────────────────────
                $highestRow      = $s->getHighestRow();
                $highestCol      = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                $headerRow    = 7;          // Première ligne d'en-tête
                $firstDataRow = $headerRow + 3; // Données commencent ici
                $lastDataRow  = $highestRow - 3; // Ligne TOTAL

                // ── Taux mensuel (stocké en ligne 9, colonne E de l'en-tête)
                // On récupère la valeur numérique du taux depuis le titre de colonne
                $monthlyRate = (float) $this->rows['monthly_rate'];

                // ── Formules par ligne de données ─────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    // B = CEIL(C / 0.009)
                    $s->getCell("B{$row}")->setValue("=IFERROR(CEILING(C{$row}/0.009,1),0)");
                    // E = CEIL(D / monthly_rate)
                    $s->getCell("E{$row}")->setValue("=IFERROR(CEILING(D{$row}/{$monthlyRate},1),0)");
                    // F = E / 0.009
                    $s->getCell("F{$row}")->setValue("=IFERROR(CEILING(E{$row}/0.009,1),0)");
                    // G = B + F
                    $s->getCell("G{$row}")->setValue("=B{$row}+F{$row}");

                    // Forcer type numérique pour C et D (données brutes)
                    $s->setCellValueExplicit("C{$row}", $s->getCell("C{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("D{$row}", $s->getCell("D{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // ── Ligne TOTAL : SUM pour chaque colonne ─────────────────
                $lastDataContent = $lastDataRow - 1;
                foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                    $s->getCell("{$col}{$lastDataRow}")
                        ->setValue("=SUM({$col}{$firstDataRow}:{$col}{$lastDataContent})");
                }

                // ── Colonne D : ECART dans la ligne TOTAL → ligne de vérif
                // (optionnel : rien à calculer, c'est une somme)

                // ── Style : titres ────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);
                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // ── Style : en-têtes colonnes (3 lignes) ─────────────────
                $s->getStyle("A{$headerRow}:{$highestCol}" . ($headerRow + 2))
                    ->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}" . ($headerRow + 2))
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}" . ($headerRow + 2))
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$headerRow}:{$highestCol}" . ($headerRow + 2))
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Fusions des en-têtes sur 3 lignes
                foreach (['A', 'B', 'C', 'G'] as $col) {
                    $s->mergeCells("{$col}{$headerRow}:{$col}" . ($headerRow + 2));
                }
                $s->mergeCells("D{$headerRow}:F{$headerRow}");
                $s->mergeCells("D" . ($headerRow + 1) . ":F" . ($headerRow + 1));

                // ── Bordures ──────────────────────────────────────────────
                $s->getStyle("A{$headerRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Style : lignes de données ─────────────────────────────
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$row}:{$highestCol}{$row}")->getFont()->setSize(14);
                }

                // DATE colonne A : alignée à gauche
                for ($row = $firstDataRow; $row <= $lastDataRow - 1; $row++) {
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // ── Style : ligne TOTAL ───────────────────────────────────
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                // ── Hauteurs ──────────────────────────────────────────────
                $s->getRowDimension($headerRow)->setRowHeight(25);
                for ($row = $headerRow; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(24);
                }

                // ── Signature ─────────────────────────────────────────────
                $sigRow1 = $highestRow - 1;
                $sigRow2 = $highestRow;
                $sigStart = Coordinate::stringFromColumnIndex($highestColIndex - 2);
                $sigEnd   = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("A{$sigRow1}:B{$sigRow1}");
                $s->mergeCells("{$sigStart}{$sigRow1}:{$sigEnd}{$sigRow1}");
                $s->getStyle("{$sigStart}{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$sigStart}{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                $s->mergeCells("A{$sigRow2}:B{$sigRow2}");
                $s->mergeCells("{$sigStart}{$sigRow2}:{$sigEnd}{$sigRow2}");
                $s->getStyle("{$sigStart}{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$sigStart}{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
