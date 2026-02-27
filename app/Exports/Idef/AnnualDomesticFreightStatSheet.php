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

class AnnualDomesticFreightStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $subTitle;
    protected $rows;
    protected $operators;
    protected $annexeNumber;


    public function __construct(string $sheetTitle, string $title, string $subTitle, array $rows, array $operators = [], string $annexeNumber = null)
    {
        $this->operators = $operators;
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->subTitle = $subTitle;
        $this->rows = $rows;
        $this->annexeNumber = $annexeNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    public function array(): array
    {
        $cols = 5; // MOIS + TOTAL FRET + TOTAL FRET IDEF + ECART (EXONERE) + PERCEPTION ESTIMEE HORS DGDA
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
        // EN-TÊTES
        $data[] = ['MOIS', 'TOTAL FRET', 'TOTAL FRET IDEF', 'ECART (EXONERE)', 'PERCEPTION ESTIMEE HORS DGDA'];
        $data[] = ['', 'EMBARQUE', 'EMBARQUE', '', '(0,009/kg)']; // Ligne vide pour séparer les titres des données
        foreach ($this->rows as $row) {
            $month = [$this->getMonthName($row['MOIS'])];
            array_shift($row); // Remove the first row which contains the MOIS
            $totalFreight = $this->getFreightDatas($row);

            $data[] = array_merge($month, $totalFreight);
        }
        $data[] = ['TOTAL', '', '', '', '']; // Total line


        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
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
                // LIGNES DE DONNÉES
                $headerRow = 8; // La ligne où commencent les données (après les titres)          
                $firstDataRow = $headerRow + 2; // La ligne où commencent les données (après les titres et la ligne vide)

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
                $s->mergeCells("A7:{$highestCol}7");
                $s->getStyle("A7")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A7")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER) // ✅ Centré
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A7")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

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

                $s->getStyle("A9:{$highestCol}9")
                    ->getFont()->setBold(true)->setSize(13);
                $s->getStyle("A9:{$highestCol}9")
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
                for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getFont()
                        ->setSize(14);

                    for ($colIndex = 2; $colIndex <= $highestColIndex; $colIndex++) {
                        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                        $s->setCellValueExplicit("{$colLetter}{$row}", $s->getCell("{$colLetter}{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    }
                }

                // ═══════════════════════════════════════════════════════════
                // ✅ FORMULES EXCEL POUR LES TOTAUX
                // ═══════════════════════════════════════════════════════════

                // Ligne TOTAUX : somme verticale pour chaque colonne
                for ($col = 2; $col <= $highestColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $latestDataRow = $lastDataRow - 1; // La ligne avant la ligne TOTAL
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
                // dd($signatureRow1, $signatureRow2, $highestColIndex);

                // ✅ 2 colonnes à partir de la droite
                $signatureStartColIndex = $highestColIndex;
                $signatureStartCol = Coordinate::stringFromColumnIndex($signatureStartColIndex);
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);
                // Ligne 1 : LE CHEF DE BUREAU IDEF
                $s->mergeCells("{$signatureStartCol}{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("{$signatureStartCol}{$signatureRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : BANZE LUKUNGAY
                $s->mergeCells("{$signatureStartCol}{$signatureRow2}:{$signatureEndCol}{$signatureRow2}");
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("{$signatureStartCol}{$signatureRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        ];
    }

    private function getFreightDatas($fretDatas): array
    {
        $trafficFret = 0;
        $idefFret = 0;
        foreach ($fretDatas as $key => $value) {
            if ($key === 'UN') {
                $trafficFret += (int)$value;
            } else {
                $trafficFret += (int)$value;
                $idefFret += (int)$value;
            }
        }

        return [
            $trafficFret,
            $idefFret,
            $trafficFret - $idefFret,
            ($idefFret) * 0.009
        ];
    }

    private function getMonthName($row): string
    {
        $rowExploded = explode('-', $row);
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

        return $monthNames[$rowExploded[0]];
    }
}
