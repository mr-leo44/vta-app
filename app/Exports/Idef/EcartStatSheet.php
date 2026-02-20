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

class EcartStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $rows;

    public function __construct(string $sheetTitle, string $title, array $rows)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->rows = $rows;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31); //$this->sheetTitle;
    }

    public function array(): array
    {
        $cols = 5; // DATE + PAX + GOPASS + ECART + JUSTIFICATIFS
        $data = [];

        // TITRES
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
        // EN-TÊTES
        $data[] = ['DATE', 'NOMBRE PAX EMBARQUES', 'NOMBRE DES GO-PASS RAMASSES', 'ECART', 'JUSTIFICATIONS'];

        // ✅ DONNÉES : On ne met QUE les dates, le reste sera écrit dans AfterSheet
        foreach ($this->rows as $row) {
            $dataRow = [$row['DATE'] ?? ''];
            $totalPax = 0;
            $totalGopass = 0;
            array_shift($row); // Remove the first row which contains the date
            foreach ($row as $key => $value) {
                $totalPax += $value['trafic'];
                $totalGopass += $value['gopass'];
            }
            $dataRow[] = $totalPax;
            $dataRow[] = $totalGopass;
            $dataRow[] = $totalPax - $totalGopass;
            $dataRow[] = $this->getDayJustifications($row); // Placeholder for justifications
            $data[] = $dataRow;
        }
        $data[] = ['TOTAL', '', '', '', '']; // Total line

        // SIGNATURE
        $data[] = array_fill(0, $cols, "");

        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 1] = 'LE CHEF DE BUREAU IDEF';
        $data[] = $sig1;

        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 1] = 'BANZE LUKUNGAY';
        $data[] = $sig2;
        return $data;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // ORIENTATION + FIT TO PAGE
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
                $highestRow = $s->getHighestRow();
                $lastDataRow = $highestRow - 3; // La ligne avant les 2 lignes de signature + la ligne TOTAL
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
                $s->getStyle("B5")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                // Ligne 5 : TITRE PRINCIPAL (CENTRÉ)
                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER) // ✅ Centré
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                // LIGNES DE DONNÉES
                $headerRow = 7; // La ligne où commencent les en-têtes de colonnes (LIBELLE, PAX/FRET TOTAL, etc.)
                $firstDataRow = $headerRow + 1;

                // STYLE EN-TÊTES
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // BORDURES
                $s->getStyle("A{$headerRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $s->getStyle("C8:{$highestCol}8")
                    ->getFont()->setSize(14);

                // STYLE DES LIGNES DE DONNÉES
                for ($row = $headerRow + 1; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:D{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$row}:D{$row}")
                        ->getFont()
                        ->setSize(14);
                    $s->getStyle("E{$row}")
                        ->getFont()->setSize(14);
                    $s->getStyle("E{$row}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);

                    for ($colIndex = 2; $colIndex <= $highestColIndex - 1; $colIndex++) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $s->setCellValueExplicit("{$colLetter}{$row}", $s->getCell("{$colLetter}{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // ✅ FORMULES EXCEL POUR LES TOTAUX
                // ═══════════════════════════════════════════════════════════

                // Ligne TOTAUX : somme verticale pour chaque colonne
                for ($col = 2; $col <= $highestColIndex - 1; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $latestDataRow = $lastDataRow - 1;
                    $s->setCellValue(
                        "{$colLetter}{$lastDataRow}",
                        "=SUM({$colLetter}{$firstDataRow}:{$colLetter}{$latestDataRow})"
                    );
                }

                // ═══════════════════════════════════════════════════════════
                // STYLE LIGNE TOTAUX
                // ═══════════════════════════════════════════════════════════
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN);
                $s->getStyle("A{$lastDataRow}:{$highestCol}{$lastDataRow}")
                    ->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_THIN);
                // Hauteur des lignes
                $s->getRowDimension($headerRow)->setRowHeight(25);

                for ($row = 8; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(24);
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

    private function getDayJustifications($row): string
    {
        $sumOfJustifications = [];
        foreach ($row as $value) { // Parcours des valeurs de la ligne pour trouver les justifications
            if (isset($value['justifications']) && !empty($value['justifications'])) { // Vérifie si des justifications existent
                foreach ($value['justifications'] as $key => $justification) {
                    if ($key === "Militaires") {
                        if (!isset($sumOfJustifications[$key])) {
                            $sumOfJustifications[$key] = [
                                'sfr' => 0,
                                'value' => 0
                            ];
                        }
                        $sumOfJustifications[$key]['sfr'] += $justification['sfr'];
                        $sumOfJustifications[$key]['value'] += $justification['value'];
                    } else {
                        if (!isset($sumOfJustifications[$key])) {
                            $sumOfJustifications[$key] = 0;
                        }
                        $sumOfJustifications[$key] += $justification;
                    }
                }
            }
        }

        $justificationParts = [];

        foreach ($sumOfJustifications as $key => $value) {
            if ($key === "Militaires") {
                $count = $value['value'];
                $sfr = $value['sfr'];
                $label = $count > 1 ? "Militaires" : "Militaire";
                $justificationParts[] = "{$value['value']} {$label} ({$sfr} sfr)";
            } else {
                $justificationParts[] = "{$value} {$key}";
            }
        }

        $justificationStrings = implode(', ', $justificationParts);
        return $sumOfJustifications == [] ? "RAS" : $justificationStrings;
    }
}
