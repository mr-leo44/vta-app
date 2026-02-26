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

class ExonerationStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $subTitle;
    protected $rows;
    protected $operators;
    protected $annexeNumber;
    // Mapping des lignes 'DATE' => colonnes vides (indices numériques, ex: 3..18)
    protected $dateRowsInfoFromArray = [];


    public function __construct(string $sheetTitle, string $title, string $subTitle, array $rows, array $operators, string $annexeNumber)
    {
        $this->rows = $rows;
        $this->operators = $operators;
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->subTitle = $subTitle;
        $this->annexeNumber = $annexeNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    private function buildQuinzaine(array $dateNumbers, string $label, int $totalDays = null): array
    {
        $cols = 19;
        $data = [];
        $isFirstQuinzaine = $label === '1ère QUINZAINE';

        $data[] = array_pad([$label], $cols, '');

        $date = ['DATE', 'FRET'];
        // Filtrer les dates qui ne doivent pas être affichées
        $displayDates = array_map(function($day) use ($totalDays) {
            return ($totalDays === null || $day <= $totalDays) ? $day : '';
        }, $dateNumbers);
        
        // Construire la ligne DATE
        $dateRow = $date;
        $dateRow = array_merge($dateRow, $displayDates);
        
        // Ajouter colonne vide et SOUS TOTAL seulement pour 1ère QUINZAINE
        if ($isFirstQuinzaine) {
            $dateRow[] = ''; // Colonne vide avant sous-total
        }
        $dateRow[] = $isFirstQuinzaine ? 'SOUS TOTAL 1' : 'SOUS TOTAL 2';
        $data[] = array_pad($dateRow, $cols, '');
        
        $data[] = array_pad(['CATEGORIE D\'EXON'], $cols, '');

        $exonDepDatas = array_fill(0, count($dateNumbers), '');
        $exonArrDatas = array_fill(0, count($dateNumbers), '');
        $totalDepExon = 0;
        $totalArrExon = 0;

        for ($i = 0; $i < count($dateNumbers); $i++) {
            // Ignorer les jours qui dépassent totalDays
            if ($totalDays !== null && $dateNumbers[$i] > $totalDays) {
                $exonDepDatas[$i] = '';
                $exonArrDatas[$i] = '';
                continue;
            }
            
            $rowIndex = $dateNumbers[$i] - 1;
            if (isset($this->rows[$rowIndex])) {
                foreach ($this->rows[$rowIndex] as $key => $op) {
                    if ($key !== 'UN') continue;
                    if (is_array($op)) {
                        $exonDepDatas[$i] = (int)$op['departure'];
                        $exonArrDatas[$i] = (int)$op['arrival'];
                        $totalDepExon += $exonDepDatas[$i];
                        $totalArrExon += $exonArrDatas[$i];
                    } else {
                        $exonDepDatas[$i] = (int)$op;
                        $totalDepExon += $exonDepDatas[$i];
                    }
                }
            } else {
                $exonDepDatas[$i] = '';
                $exonArrDatas[$i] = '';
            }
        }

        $embarqueRow = ['MONUSCO', 'EMBARQUE'];
        $embarqueRow = array_merge($embarqueRow, $exonDepDatas);
        
        // Ajouter colonne vide seulement pour 1ère QUINZAINE
        if ($isFirstQuinzaine) {
            $embarqueRow[] = '';
        }
        $embarqueRow[] = $totalDepExon;
        $data[] = array_pad($embarqueRow, $cols, '');
        
        if (str_contains($this->sheetTitle, 'EXON INT')) {
            $debarqueRow = ['MONUSCO', 'DEBARQUE'];
            $debarqueRow = array_merge($debarqueRow, $exonArrDatas);
            
            // Ajouter colonne vide seulement pour 1ère QUINZAINE
            if ($isFirstQuinzaine) {
                $debarqueRow[] = '';
            }
            $debarqueRow[] = $totalArrExon;
            $data[] = array_pad($debarqueRow, $cols, '');
        }
        
        // Construire la ligne TOTAL avec les sommes verticales
        $totalRow = ['TOTAL', ''];
        $totalHorizontal = 0;
        for ($i = 0; $i < count($dateNumbers); $i++) {
            $depValue = ($exonDepDatas[$i] === '' ? 0 : $exonDepDatas[$i]);
            $arrValue = ($exonArrDatas[$i] === '' ? 0 : $exonArrDatas[$i]);
            $sum = $depValue + $arrValue;
            $totalRow[] = $sum;
            $totalHorizontal += $sum;
        }
        
        // Ajouter colonne vide seulement pour 1ère QUINZAINE
        if ($isFirstQuinzaine) {
            $totalRow[] = '';
        }
        $totalRow[] = $totalHorizontal;
        $data[] = array_pad($totalRow, $cols, '');

        return $data;
    }

    public function array(): array
    {
        $cols = 19;
        $data = [];

        // TITRES
        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU IDEF'],
                ["RVA AERO/N'DJILI"],
                ["DIVISION COMMERCIALE"],
                ['', $this->annexeNumber],
                [$this->title],
                [$this->subTitle]
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        // 1ère QUINZAINE (jours 1-15)
        $qOneDates = range(1, 15);
        $data = array_merge($data, $this->buildQuinzaine($qOneDates, '1ère QUINZAINE'));

        // Ligne d'espacement
        $data[] = array_fill(0, $cols, '');

        // 2e QUINZAINE (jours 16 au dernier jour)
        $totalDays = count($this->rows);
        $qTwoDates = range(16, 31); // Toujours 16 jours possibles
        $data = array_merge($data, $this->buildQuinzaine($qTwoDates, '2ème QUINZAINE', $totalDays));

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
        foreach (['LE CHEF DE BUREAU IDEF', 'BANZE LUKUNGAY'] as $sig) {
            $sigRow = array_fill(0, $cols, '');
            $sigRow[$cols - 1] = $sig;
            $data[] = $sigRow;
        }

        // Construire la map des lignes DATE -> colonnes vides à partir des données construites
        $this->dateRowsInfoFromArray = [];
        for ($i = 0; $i < count($data); $i++) {
            $rowNumber = $i + 1; // 1-based
            $firstCell = isset($data[$i][0]) ? trim((string)$data[$i][0]) : '';
            if ($firstCell === 'DATE') {
                $emptyCols = [];
                for ($col = 3; $col <= 18; $col++) {
                    $idx = $col - 1; // 0-based index in array row
                    $val = isset($data[$i][$idx]) ? trim((string)$data[$i][$idx]) : '';
                    if ($val === '') {
                        $emptyCols[] = $col;
                    }
                }
                $this->dateRowsInfoFromArray[$rowNumber] = $emptyCols;
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
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setHorizontalCentered(true);

                // MARGES
                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.25);
                $s->getPageMargins()->setRight(0.25);

                // DIMENSIONS
                $highestRow = $s->getHighestRow() - 3;
                $highestCol = $s->getHighestColumn();
                $highestColIndex = Coordinate::columnIndexFromString($highestCol);

                // Lignes 1-4 : Alignées à gauche
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                 $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);
                // Ligne 5 : TITRE PRINCIPAL (CENTRÉ)
                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER) // ✅ Centré
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // Lignes 6-8 : Titres avec style uniforme
                for ($row = 6; $row <= 8; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Appliquer la couleur de fond sauf pour A8
                    if ($row !== 8) {
                        $s->getStyle("A{$row}")
                            ->getFill()->setFillType('solid')
                            ->getStartColor()->setARGB('FFD9E1F2');
                    }
                }

                // CALCULS DES TOTAUX ET MISE EN FORME
                $firstDataCol = Coordinate::stringFromColumnIndex(3); // C
                $lastDataCol = Coordinate::stringFromColumnIndex(18); // R
                $totalCol = Coordinate::stringFromColumnIndex(19); // S

                // Largeurs des colonnes C à R
                for ($col = 3; $col <= 18; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $s->getColumnDimension($colLetter)->setWidth(24);
                }

                
                $quinzaineRanges = [];
                // Utiliser la map construite dans array(): lignes 'DATE' => colonnes vides
                $dateRowsInfo = $this->dateRowsInfoFromArray ?? [];

                for ($row = 1; $row <= $highestRow; $row++) {
                    $cellA = $s->getCell("A{$row}")->getValue();

                    // Centrer les titres de quinzaines (avec merge et fond)
                    if (strpos($cellA, 'QUINZAINE') !== false) {
                        $s->mergeCells("A{$row}:{$highestCol}{$row}");
                        $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFF');
                        $s->getStyle("A{$row}")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF4472C4');
                        $s->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Lignes DATE: en gras, police 13, sans fond
                    if (trim($cellA) === 'DATE') {
                        for ($col = 1; $col <= 19; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$colLetter}{$row}")->getFont()->setBold(true)->setSize(13);
                            $s->getStyle("{$colLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        }
                        
                        // Identifier les colonnes vides pour cette quinzaine
                        $rowEmptyColumns = [];
                        for ($col = 3; $col <= 18; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $dateValue = trim((string)$s->getCell("{$colLetter}{$row}")->getValue());
                            if ($dateValue === "") {
                                $rowEmptyColumns[] = $col;
                            }
                        }
                        $dateRowsInfo[$row] = $rowEmptyColumns;
                    }

                    // Lignes CATEGORIE D'EXON: merger toute la ligne, sans fill
                    if (trim($cellA) === 'CATEGORIE D\'EXON') {
                        $s->mergeCells("A{$row}:{$highestCol}{$row}");
                        $s->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                        $s->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Lignes MONUSCO: formules et gras
                    if (strpos($cellA, 'MONUSCO') !== false) {
                        // Chercher la ligne DATE associée via la map fournie
                        $currentEmptyColumns = [];
                        for ($i = $row - 1; $i >= 1; $i--) {
                            if (isset($dateRowsInfo[$i])) {
                                $currentEmptyColumns = $dateRowsInfo[$i];
                                break;
                            }
                        }

                        // Style : gras, police 12
                        for ($col = 1; $col <= 19; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$colLetter}{$row}")->getFont()->setBold(true)->setSize(12);
                            $s->getStyle("{$colLetter}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }

                    // Lignes TOTAL: formules verticales et style gras (sans fill)
                    if (trim($cellA) === 'TOTAL') {
                        // Chercher les lignes MONUSCO au-dessus
                        $monuscoRows = [];
                        $firstDataRow = null;
                        $currentEmptyColumns = [];
                        
                        for ($i = $row - 1; $i >= 1; $i--) {
                            $prevCellA = $s->getCell("A{$i}")->getValue();
                            if (strpos($prevCellA, 'MONUSCO') !== false) {
                                $monuscoRows[] = $i;
                                $firstDataRow = $i;
                            } elseif (trim($prevCellA) === 'CATEGORIE D\'EXON') {
                                // Récupérer les colonnes vides de la ligne DATE depuis la map
                                for ($j = $i - 1; $j >= 1; $j--) {
                                    if (isset($dateRowsInfo[$j])) {
                                        $currentEmptyColumns = $dateRowsInfo[$j];
                                        break;
                                    }
                                }
                                break;
                            }
                        }

                        if (!empty($monuscoRows)) {
                            // Sommes verticales pour chaque colonne (C à R), sauf colonnes vides
                            for ($col = 3; $col <= 18; $col++) {
                                if (!in_array($col, $currentEmptyColumns)) {
                                    $colLetter = Coordinate::stringFromColumnIndex($col);
                                    $sumFormula = "=" . implode("+", array_map(fn($r) => "{$colLetter}{$r}", $monuscoRows));
                                    $s->getCell("{$colLetter}{$row}")->setValue($sumFormula);
                                }
                            }

                            // Somme horizontale finale pour colonne S (SOUS TOTAL)
                            $sumFormula = "=" . implode("+", array_map(fn($r) => "{$totalCol}{$r}", $monuscoRows));
                            $s->getCell("{$totalCol}{$row}")->setValue($sumFormula);

                            // Enregistrer la plage de quinzaine
                            if ($firstDataRow) {
                                $dateRow = $firstDataRow - 2;
                                $quinzaineRanges[] = [
                                    'start' => $dateRow,
                                    'end' => $row   
                                ];
                            }
                        }

                        // Style : gras + centré + police 13 (sans fill)
                        for ($col = 1; $col <= 19; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $s->getStyle("{$colLetter}{$row}")->getFont()->setBold(true)->setSize(13);
                            $s->getStyle("{$colLetter}{$row}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                                ->setVertical(Alignment::VERTICAL_CENTER);
                        }
                    }
                }

                // Appliquer les bordures et hauteurs à tous les groupes de données
                foreach ($quinzaineRanges as $range) {
                    for ($row = $range['start']; $row <= $range['end']; $row++) {
                        // Hauteur des lignes
                        if (trim($s->getCell("A{$row}")->getValue()) === 'DATE' || 
                            trim($s->getCell("A{$row}")->getValue()) === 'CATEGORIE D\'EXON' ||
                            trim($s->getCell("A{$row}")->getValue()) === 'TOTAL') {
                            $s->getRowDimension($row)->setRowHeight(25);
                        } elseif (strpos($s->getCell("A{$row}")->getValue(), 'MONUSCO') !== false) {
                            $s->getRowDimension($row)->setRowHeight(22);
                        }

                        // Bordures fines pour toutes les cellules
                        for ($col = 1; $col <= 19; $col++) {
                            $colLetter = Coordinate::stringFromColumnIndex($col);
                            $cellStyle = $s->getStyle("{$colLetter}{$row}");
                            $cellStyle->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cellStyle->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cellStyle->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                            $cellStyle->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF000000');
                        }
                    }
                }

                // Construire un mapping row -> colonnes vides pour chaque quinzaine
                $emptyColumnsPerQuinzaine = [];
                foreach ($quinzaineRanges as $range) {
                    $dateRow = $range['start'];
                    $emptyCols = isset($dateRowsInfo[$dateRow]) ? $dateRowsInfo[$dateRow] : [];
                    // Appliquer la même info pour toutes les lignes de la quinzaine
                    for ($r = $range['start']; $r <= $range['end']; $r++) {
                        $emptyColumnsPerQuinzaine[$r] = $emptyCols;
                    }
                }
                $s->getStyle("C11:{$highestCol}{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Forcer DataType::NUMERIC pour afficher les zéros (sauf colonnes vides)
                for ($col = 3; $col <= 19; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    for ($row = 11; $row <= $highestRow; $row++) {
                        $cellA = $s->getCell("A{$row}");

                        // Ignorer les lignes vides ou les lignes de titre
                        if (!$cellA || trim((string)$cellA->getValue()) === '') {
                            continue;
                        }
                        // Si cette colonne est censée être vide pour la quinzaine courante, l'ignorer
                        $emptyColsForThisRow = $emptyColumnsPerQuinzaine[$row] ?? [];
                        if (in_array($col, $emptyColsForThisRow)) {
                            continue;
                        }

                        try {
                            $cell = $s->getCell("{$colLetter}{$row}");
                            $value = $cell->getValue();

                            // Si la valeur est une string vide, la laisser vide (ne pas ajouter 0)
                            if ($value === '') {
                                continue;
                            }

                            // Si la valeur est 0 ou nulle ET ce n'est pas une formule
                            if (($value === 0 || $value === '0' || $value === null) && strpos((string)$value, '=') === false) {
                                $cell->setDataType(DataType::TYPE_NUMERIC);
                                if ($value === null) {
                                    $cell->setValue(0);
                                }
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                // ✅ 2 & 3. SIGNATURE : 2 colonnes depuis la droite, fusionnées et centrées
                $signatureRow1 = $highestRow - 1;
                $signatureRow2 = $signatureRow1 + 1;

                // ✅ 2 colonnes à partir de la droite
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                // Ligne 1 : LE CHEF DE BUREAU IDEF
                $s->getStyle("{$signatureEndCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureEndCol}{$signatureRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : BANZE LUKUNGAY
                $s->getStyle("{$signatureEndCol}{$signatureRow2}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$signatureEndCol}{$signatureRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        ];
    }
}
