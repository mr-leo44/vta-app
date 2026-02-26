<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PaxbusWeeklyDomesticSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected $sheetTitle;
    protected $title;
    protected $subTitle;
    protected $data;

    public function __construct(string $sheetTitle, string $title, string $subTitle, array $data)
    {
        $this->sheetTitle = $sheetTitle;
        $this->title = $title;
        $this->subTitle = $subTitle;
        $this->data = $data;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 50);
    }

    public function array(): array
    {
        $cols = 4; // DATE | COMPAGNIES | IMMATRICULATION | PMAD
        $arrayData = [];

        // TITRES
        foreach ([
            ['SERVICE VTA'],
            ['BUREAU PAX BUS'],
            ["RVA AERO/N'DJILI"],
            [''],
            [$this->title],
            [$this->subTitle],
        ] as $line) {
            $arrayData[] = array_pad($line, $cols, '');
        }

        // EN-TÊTES
        $arrayData[] = ['DATE', 'COMPAGNIES', 'IMMATRICULATION', 'PMAD'];

        // Organiser les données par compagnie et PMAD
        $organizedData = $this->organizeByOperatorAndPMAD();

        // DONNÉES - Classées par PMAD (≥50T d'abord, puis <50T)
        foreach (['≥50T', '<50T'] as $category) {
            if (!isset($organizedData[$category])) continue;

            foreach ($organizedData[$category] as $date => $operators) {
                $isFirstOperatorForDate = true;

                foreach ($operators as $operatorSigle => $flights) {
                    foreach ($flights as $flight) {
                        $row = [
                            $isFirstOperatorForDate ? $date : '',
                            $operatorSigle,
                            $flight['immatriculation'],
                            $flight['category'],
                        ];

                        $arrayData[] = $row;
                        $isFirstOperatorForDate = false;
                    }
                }
            }
        }

        // LIGNES VIDES AVANT SIGNATURES
        $arrayData[] = array_fill(0, $cols, '');

        // SIGNATURES - LIGNE 1
        $sig1 = array_fill(0, $cols, '');
        $sig1[0] = 'LE CHEF DE SERVICE VTA ai';
        $sig1[$cols - 1] = 'LE CHEF DE BUREAU PAX BUS ai';
        $arrayData[] = $sig1;

        // LIGNE VIDE
        $arrayData[] = array_fill(0, $cols, '');

        // SIGNATURES - LIGNE 2
        $sig2 = array_fill(0, $cols, '');
        $sig2[0] = 'SAGESSE MINSAY NKASER';
        $sig2[$cols - 1] = 'FREDDY KALEMA TABU';
        $arrayData[] = $sig2;

        return $arrayData;
    }

    /**
     * Organise les données par catégorie PMAD, puis par date
     */
    private function organizeByOperatorAndPMAD(): array
    {
        $organized = [
            '≥50T' => [],
            '<50T' => [],
        ];

        foreach ($this->data['data'] as $date => $operators) {
            foreach ($operators as $operatorSigle => $flights) {
                foreach ($flights as $flight) {
                    $category = $flight['category'];

                    if (!isset($organized[$category][$date])) {
                        $organized[$category][$date] = [];
                    }

                    if (!isset($organized[$category][$date][$operatorSigle])) {
                        $organized[$category][$date][$operatorSigle] = [];
                    }

                    $organized[$category][$date][$operatorSigle][] = $flight;
                }
            }
        }

        return $organized;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $s = $event->sheet->getDelegate();

                // PAGE
                $s->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);

                // MARGES
                $s->getPageMargins()->setTop(0.25);
                $s->getPageMargins()->setBottom(0.25);
                $s->getPageMargins()->setLeft(0.5);
                $s->getPageMargins()->setRight(0.5);

                $highestRow = $s->getHighestRow();
                $highestCol = $s->getHighestColumn();

                $headerRow = 7;
                $firstDataRow = 8;
                $lastDataRow = $highestRow - 4; // Avant les signatures

                // STYLE DES TITRES
                for ($row = 1; $row <= 3; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(10);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Ligne 5 : TITRE PRINCIPAL
                $s->mergeCells("A5:{$highestCol}5");
                $s->getStyle('A5')->getFont()->setBold(true)->setSize(12);
                $s->getStyle('A5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle('A5')
                    ->getFill()->setFillType('solid');

                // Ligne 6 : SOUS-TITRE
                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle('A6')->getFont()->setBold(true)->setSize(11);
                $s->getStyle('A6')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // EN-TÊTES
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FF4472C4');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getFont()->getColor()->setARGB('FFFFFFFF');
                $s->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // BORDURES ET DONNÉES
                for ($row = $headerRow; $row <= $lastDataRow; $row++) {
                    $s->getStyle("A{$row}:{$highestCol}{$row}")
                        ->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    if ($row >= $firstDataRow) {
                        // Alignement centré pour PMAD
                        $s->getStyle("D{$row}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // SIGNATURES
                $sig1Row = $lastDataRow + 2;
                $sig2Row = $sig1Row + 2;

                // Première ligne de signature
                $s->getStyle("A{$sig1Row}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("D{$sig1Row}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$sig1Row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle("D{$sig1Row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Deuxième ligne de signature
                $s->getStyle("A{$sig2Row}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("D{$sig2Row}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("A{$sig2Row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $s->getStyle("D{$sig2Row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}