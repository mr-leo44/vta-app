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

class SynthStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected $sheetTitle;
    protected $title;
    protected $domesticRows;
    protected $internationalRows;

    public function __construct(string $sheetTitle, string $title, array $domesticRows, array $internationalRows)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->domesticRows = $domesticRows;
        $this->internationalRows = $internationalRows;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31); //$this->sheetTitle;
    }

    public function array(): array
    {
        $cols = 4; // DATE + 3 domestic + 1 international + TOTAL
        $data = [];

        // TITRES
        foreach (
            [
                ['SERVICE VTA'],
                ['BUREAU IDEF'],
                ["RVA AERO/N'DJILI"],
                ["DIVISION COMMERCIALE"],
                ['', 'ANNEXE XI'],
                [$this->title],
            ] as $line
        ) {
            $data[] = array_pad($line, $cols, '');
        }

        // EN-TÊTES LIGNE 1 
        $header1 = ['LIBELLE', 'PAX /FRET TOTAL (a)', 'TOTAL GO-PASS/FRET IDEF ', 'TOTAL (PAX /FRET)'];
        $data[] = $header1;
        // EN-TÊTES LIGNE 2 (sous-colonnes)
        $header2 = ['', '', 'FACTURE (b)', 'EXONERE(a-b)'];
        $data[] = $header2;
        // EN-TÊTES LIGNE 3 (sous-colonnes)
        $header3 = ['1. VOLS NATIONAUX', '', '', ''];
        $data[] = $header3;

        // DONNÉES PAX NATIONAUX
        $domesticPaxRow = $this->getPaxAndGopassDatas($this->domesticRows['pax'] ?? []);
        $data[] = $domesticPaxRow;

        // DONNEES FRET NATIONAUX
        $domesticFretDatas = $this->domesticRows['fret_depart'] ?? [];
        $domesticExcedentData = $this->domesticRows['exced_depart'] ?? [];

        $domesticFretRow = $this->getFretDatas($domesticFretDatas, $domesticExcedentData, 'b. FRET EMBARQUE');
        // $domesticFretRow = ['b. FRET EMBARQUE', $domesticTrafficFret, $DomesticIdefFret, $domesticTrafficFret - $DomesticIdefFret];
        $data[] = $domesticFretRow;

        $header4 = ['2. VOLS INTERNATIONAUX', '', '', ''];
        $data[] = $header4;

        // DONNÉES PAX INTERNATIONAUX
        $internationalPaxRow = $this->getPaxAndGopassDatas($this->internationalRows['pax'] ?? []);
        $data[] = $internationalPaxRow;

        // DONNEES FRET INTERNATIONAUX
        $internationalepartureFretDatas = $this->internationalRows['fret_depart'] ?? [];
        $internationalDepartureExcedentData = $this->internationalRows['exced_depart'] ?? [];
        $internationalArrivalFretDatas = $this->internationalRows['fret_arrivee'] ?? [];
        $internationalArrivalExcedentData = $this->internationalRows['exced_arrivee'] ?? [];

        $internationalDepartureRow = $this->getFretDatas($internationalepartureFretDatas, $internationalDepartureExcedentData, 'b. FRET EMBARQUE');
        $internationalArrivalRow = $this->getFretDatas($internationalArrivalFretDatas, $internationalArrivalExcedentData, 'c. FRET DEBARQUE');
        $data[] = $internationalDepartureRow;
        $data[] = $internationalArrivalRow;

        // LIGNES VIDES AVANT SIGNATURE
        $data[] = array_fill(0, $cols, '');

        // SIGNATURE
        $sig1 = array_fill(0, $cols, '');
        $sig1[$cols - 2] = 'LE CHEF DE BUREAU IDEF';
        $data[] = $sig1;

        $sig2 = array_fill(0, $cols, '');
        $sig2[$cols - 2] = 'BANZE LUKUNGAY';
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
                // LIGNES DE DONNÉES
                $headerRow = 7; // La ligne où commencent les en-têtes de colonnes (LIBELLE, PAX/FRET TOTAL, etc.)
                $lastDataRow = $highestRow - 3;

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
                $s->getStyle("A9:{$highestCol}15")
                    ->getFont()->setSize(14);
                $s->mergeCells("A9:{$highestCol}9");
                $s->mergeCells("A12:{$highestCol}12");

                $s->getStyle("A9")->getFont()->setBold(true);
                $s->getStyle("A12")->getFont()->setBold(true);
                $s->getStyle("{$highestCol}10:{$highestCol}15")->getFont()->setBold(true);
                $s->getStyle("B10:{$highestCol}15")->getNumberFormat()->setFormatCode('#,##0');

                for ($row = 10; $row <= 15; $row++) {
                    $s->setCellValueExplicit("{$highestCol}{$row}", $s->getCell("{$highestCol}{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // Hauteur des lignes
                $s->getRowDimension($headerRow)->setRowHeight(25);

                for ($row = 8; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(20);
                }

                // ✅ 2 & 3. SIGNATURE : 2 colonnes depuis la droite, fusionnées et centrées
                $signatureRow1 = $lastDataRow + 2;
                $signatureRow2 = $signatureRow1 + 1;

                // ✅ 2 colonnes à partir de la droite
                $signatureEndCol = Coordinate::stringFromColumnIndex($highestColIndex);

                // Ligne 1 : LE CHEF DE BUREAU IDEF
                $s->mergeCells("C{$signatureRow1}:{$signatureEndCol}{$signatureRow1}");
                $s->getStyle("C{$signatureRow1}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("C{$signatureRow1}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Ligne 2 : BANZE LUKUNGAY
                $s->mergeCells("C{$signatureRow2}:{$signatureEndCol}{$signatureRow2}");
                $s->getStyle("C{$signatureRow2}")
                    ->getFont()->setBold(true)->setSize(12);
                $s->getStyle("C{$signatureRow2}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }
        ];
    }

    private function getPaxAndGopassDatas($paxDatas): array
    {
        $datas = $paxDatas ?? [];
        $trafficPax = 0;
        $idefPax = 0;
        foreach ($datas as $dayValues) {
            array_shift($dayValues); // Remove 'DATE' key
            foreach ($dayValues as $value) {
                $trafficPax += (int)$value['trafic'];
                $idefPax += (int)$value['gopass'];
            }
        }
        return [
            'a. PAX EMBARQUES',
            $trafficPax,
            $idefPax,
            $trafficPax - $idefPax
        ];
    }

    private function getFretDatas($fretDatas, $excedentDatas, $libelle): array
    {
        $trafficFret = 0;
        $idefFret = 0;
        foreach ($fretDatas as $key => $dayValues) {
            [$trafficFret, $idefFret] = $this->freightData($dayValues, $trafficFret, $idefFret);
            // $this->freightData($dayValues);
        }

        foreach ($excedentDatas as $key => $dayValues) {
            [$trafficFret, $idefFret] = $this->freightData($dayValues, $trafficFret, $idefFret);
        }

        return [
            $libelle,
            $trafficFret,
            $idefFret,
            $trafficFret - $idefFret
        ];
    }

    private function freightData($dayValues, $trafficFret, $idefFret): array
    {
        array_shift($dayValues); // Remove 'DATE' key
        foreach ($dayValues as $key => $value) {
            if ($key === 'UN') {
                $trafficFret += (int)$value;
            } else {
                $trafficFret += (int)$value;
                $idefFret += (int)$value;
            }
        }

        return [$trafficFret, $idefFret];
    }
}
