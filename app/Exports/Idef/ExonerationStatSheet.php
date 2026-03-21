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
 * Feuille mensuelle Exonérations (MONUSCO).
 *
 * Structure par quinzaine :
 *   Ligne titre quinzaine
 *   Ligne DATE       : C3..R17 ou C18..R31 (jours)
 *   Ligne CAT EXON
 *   Ligne MONUSCO EMBARQUE  : valeurs brutes par jour  (col C..R)
 *   [Ligne MONUSCO DEBARQUE : valeurs brutes par jour  (col C..R)]  ← si INT
 *   Ligne TOTAL             : somme verticale MONUSCO + somme horizontale
 *
 * Seules les valeurs journalières (EMBARQUE/DEBARQUE) sont injectées
 * comme données brutes. Les sous-totaux de quinzaine (col S) et la ligne
 * TOTAL (colonnes C..R + S) sont posés comme formules Excel dans AfterSheet.
 */
class ExonerationStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string  $sheetTitle;
    protected string  $title;
    protected string  $subTitle;
    protected array   $rows;
    protected array   $operators;
    protected string  $annexeNumber;
    protected array   $dateRowsInfoFromArray = [];

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

    // ─────────────────────────────────────────────────────────────────────────
    // Construit une quinzaine : données brutes uniquement (pas de totaux PHP)
    // ─────────────────────────────────────────────────────────────────────────
    private function buildQuinzaine(array $dateNumbers, string $label, ?int $totalDays = null): array
    {
        $cols = 19;
        $data = [];
        $isFirstQuinzaine = $label === '1ère QUINZAINE';

        // Titre quinzaine
        $data[] = array_pad([$label], $cols, '');

        // Ligne DATE
        $displayDates = array_map(
            fn($day) => ($totalDays === null || $day <= $totalDays) ? $day : '',
            $dateNumbers
        );
        $dateRow = array_merge(['DATE', 'FRET'], $displayDates);
        if ($isFirstQuinzaine) $dateRow[] = '';
        $dateRow[] = $isFirstQuinzaine ? 'SOUS TOTAL 1' : 'SOUS TOTAL 2';
        $data[]   = array_pad($dateRow, $cols, '');

        $data[] = array_pad(["CATEGORIE D'EXON"], $cols, '');

        // Données brutes par jour (col C..R = indices 2..17)
        $exonDepDatas = [];
        $exonArrDatas = [];

        for ($i = 0; $i < count($dateNumbers); $i++) {
            $day = $dateNumbers[$i];
            if ($totalDays !== null && $day > $totalDays) {
                // Jour inexistant dans ce mois → cellule vraiment vide (pas de 0)
                $exonDepDatas[$i] = '';
                $exonArrDatas[$i] = '';
                continue;
            }
            // Jour existant sans donnée → 0 plutôt que vide
            $rowIndex = $day - 1;
            $exonDepDatas[$i] = 0;
            $exonArrDatas[$i] = 0;
            if (isset($this->rows[$rowIndex])) {
                foreach ($this->rows[$rowIndex] as $key => $op) {
                    if ($key !== 'UN') continue;
                    if (is_array($op)) {
                        $exonDepDatas[$i] = (int) $op['departure'];
                        $exonArrDatas[$i] = (int) $op['arrival'];
                    } else {
                        $exonDepDatas[$i] = (int) $op;
                    }
                }
            }
        }

        // Ligne EMBARQUE — données brutes, sous-total = '' (formule dans AfterSheet)
        $embarqueRow = array_merge(['MONUSCO', 'EMBARQUE'], $exonDepDatas);
        if ($isFirstQuinzaine) $embarqueRow[] = '';
        $embarqueRow[] = ''; // sous-total ← formule Excel
        $data[]        = array_pad($embarqueRow, $cols, '');

        // Ligne DEBARQUE (internationale uniquement)
        if (str_contains($this->sheetTitle, 'EXON INT')) {
            $debarqueRow = array_merge(['MONUSCO', 'DEBARQUE'], $exonArrDatas);
            if ($isFirstQuinzaine) $debarqueRow[] = '';
            $debarqueRow[] = ''; // sous-total ← formule Excel
            $data[]        = array_pad($debarqueRow, $cols, '');
        }

        // Ligne TOTAL — toutes cellules vides (formules Excel dans AfterSheet)
        $totalRow = array_pad(['TOTAL', ''], $cols, '');
        $data[]   = $totalRow;

        return $data;
    }

    public function array(): array
    {
        $cols = 19;
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

        $data = array_merge($data, $this->buildQuinzaine(range(1, 15), '1ère QUINZAINE'));
        $data[] = array_fill(0, $cols, '');

        $totalDays = count($this->rows);
        $data      = array_merge($data, $this->buildQuinzaine(range(16, 31), '2ème QUINZAINE', $totalDays));

        $data[] = array_fill(0, $cols, '');

        foreach (['LE CHEF DE BUREAU IDEF', 'BANZE LUKUNGAY'] as $sig) {
            $sigRow            = array_fill(0, $cols, '');
            $sigRow[$cols - 1] = $sig;
            $data[]            = $sigRow;
        }

        // Construire map dateRow -> colonnes vides pour AfterSheet
        $this->dateRowsInfoFromArray = [];
        foreach ($data as $i => $row) {
            if (isset($row[0]) && trim((string) $row[0]) === 'DATE') {
                $emptyCols = [];
                for ($col = 3; $col <= 18; $col++) {
                    $val = trim((string) ($row[$col - 1] ?? ''));
                    if ($val === '') $emptyCols[] = $col;
                }
                $this->dateRowsInfoFromArray[$i + 1] = $emptyCols; // 1-based
            }
        }

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
                $totalCol        = Coordinate::stringFromColumnIndex(19); // S

                // ── Largeurs fixes colonnes C..R ──────────────────────────
                for ($col = 3; $col <= 18; $col++) {
                    $s->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(24);
                }

                // ── Styles titres ─────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }
                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);

                foreach ([6, 7, 8] as $r) {
                    $s->mergeCells("A{$r}:{$highestCol}{$r}");
                    $s->getStyle("A{$r}")->getFont()->setBold(true)->setSize(16);
                    $s->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                    if ($r !== 8) {
                        $s->getStyle("A{$r}")->getFill()->setFillType('solid')
                            ->getStartColor()->setARGB('FFD9E1F2');
                    }
                }

                // ── Scan des lignes pour appliquer styles + formules ──────
                $dateRowsInfo    = $this->dateRowsInfoFromArray;
                $quinzaineRanges = [];

                for ($row = 1; $row <= $highestRow; $row++) {
                    $cellA = trim((string) $s->getCell("A{$row}")->getValue());

                    // Titre quinzaine
                    if (str_contains($cellA, 'QUINZAINE')) {
                        $s->mergeCells("A{$row}:{$highestCol}{$row}");
                        $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16)
                            ->getColor()->setARGB('FFFFFFFF');
                        $s->getStyle("A{$row}")->getFill()->setFillType('solid')
                            ->getStartColor()->setARGB('FF4472C4');
                        $s->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Ligne DATE
                    if ($cellA === 'DATE') {
                        $emptyCols = [];
                        for ($col = 3; $col <= 18; $col++) {
                            $v = trim((string) $s->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue());
                            if ($v === '') $emptyCols[] = $col;
                        }
                        $dateRowsInfo[$row] = $emptyCols;

                        for ($col = 1; $col <= 19; $col++) {
                            $cl = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$cl}{$row}")->getFont()->setBold(true)->setSize(13);
                            $s->getStyle("{$cl}{$row}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }

                    // Ligne CATEGORIE D'EXON
                    if ($cellA === "CATEGORIE D'EXON") {
                        $s->mergeCells("A{$row}:{$highestCol}{$row}");
                        $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                        $s->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Lignes MONUSCO : forcer TYPE_NUMERIC + style + formule SOUS-TOTAL (col S)
                    if (str_contains($cellA, 'MONUSCO')) {
                        // Trouver les colonnes vides depuis la ligne DATE au-dessus
                        $currentEmptyCols = [];
                        for ($i = $row - 1; $i >= 1; $i--) {
                            if (isset($dateRowsInfo[$i])) {
                                $currentEmptyCols = $dateRowsInfo[$i];
                                break;
                            }
                        }

                        // Forcer numérique sur colonnes C..R (sauf jours inexistants)
                        for ($col = 3; $col <= 18; $col++) {
                            if (in_array($col, $currentEmptyCols)) continue;
                            $cl    = Coordinate::stringFromColumnIndex($col);
                            $value = $s->getCell("{$cl}{$row}")->getValue();
                            // Écrire 0 si vide ou null (jour existant sans donnée), sauf si colonne vide = jour inexistant
                            $numeric = ($value === '' || $value === null) ? 0 : (int) $value;
                            $s->setCellValueExplicit("{$cl}{$row}", $numeric, DataType::TYPE_NUMERIC);
                        }

                        // Formule SOUS-TOTAL colonne S = SUM(C{row}:R{row}) en excluant les vides
                        $nonEmptyCols = array_filter(range(3, 18), fn($c) => !in_array($c, $currentEmptyCols));
                        if (!empty($nonEmptyCols)) {
                            $sumParts = array_map(
                                fn($c) => Coordinate::stringFromColumnIndex($c) . $row,
                                $nonEmptyCols
                            );
                            $s->getCell("{$totalCol}{$row}")->setValue('=' . implode('+', $sumParts));
                        }

                        // Style
                        for ($col = 1; $col <= 19; $col++) {
                            $cl = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$cl}{$row}")->getFont()->setBold(true)->setSize(12);
                            $s->getStyle("{$cl}{$row}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }

                    // Ligne TOTAL : formules verticales (somme des lignes MONUSCO) + horizontal
                    if ($cellA === 'TOTAL') {
                        $monuscoRows   = [];
                        $currentEmptyCols = [];

                        for ($i = $row - 1; $i >= 1; $i--) {
                            $prevA = trim((string) $s->getCell("A{$i}")->getValue());
                            if (str_contains($prevA, 'MONUSCO')) {
                                $monuscoRows[] = $i;
                            } elseif ($prevA === "CATEGORIE D'EXON") {
                                for ($j = $i - 1; $j >= 1; $j--) {
                                    if (isset($dateRowsInfo[$j])) {
                                        $currentEmptyCols = $dateRowsInfo[$j];
                                        break;
                                    }
                                }
                                break;
                            }
                        }

                        if (!empty($monuscoRows)) {
                            // Formules par colonne C..R
                            for ($col = 3; $col <= 18; $col++) {
                                if (in_array($col, $currentEmptyCols)) continue;
                                $cl      = Coordinate::stringFromColumnIndex($col);
                                $formula = '=' . implode('+', array_map(fn($r) => "{$cl}{$r}", $monuscoRows));
                                $s->getCell("{$cl}{$row}")->setValue($formula);
                            }
                            // Formule SOUS-TOTAL col S = somme des S des MONUSCO
                            $formula = '=' . implode('+', array_map(fn($r) => "{$totalCol}{$r}", $monuscoRows));
                            $s->getCell("{$totalCol}{$row}")->setValue($formula);

                            // Enregistrer plage pour bordures
                            if (!empty($monuscoRows)) {
                                $dateRow         = min($monuscoRows) - 2;
                                $quinzaineRanges[] = ['start' => $dateRow, 'end' => $row];
                            }
                        }

                        for ($col = 1; $col <= 19; $col++) {
                            $cl = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$cl}{$row}")->getFont()->setBold(true)->setSize(13);
                            $s->getStyle("{$cl}{$row}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }
                }

                // ── Bordures + hauteurs par quinzaine ─────────────────────
                foreach ($quinzaineRanges as $range) {
                    for ($row = $range['start']; $row <= $range['end']; $row++) {
                        $rv = trim((string) $s->getCell("A{$row}")->getValue());
                        if ($rv === 'DATE' || $rv === "CATEGORIE D'EXON" || $rv === 'TOTAL') {
                            $s->getRowDimension($row)->setRowHeight(25);
                        } elseif (str_contains($rv, 'MONUSCO')) {
                            $s->getRowDimension($row)->setRowHeight(22);
                        }

                        for ($col = 1; $col <= 19; $col++) {
                            $cl   = Coordinate::stringFromColumnIndex($col);
                            $cell = $s->getStyle("{$cl}{$row}");
                            $cell->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cell->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cell->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cell->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                        }
                    }
                }

                // ── Format numérique ──────────────────────────────────────
                $s->getStyle("C11:{$highestCol}{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                // ── Signature ─────────────────────────────────────────────
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
}