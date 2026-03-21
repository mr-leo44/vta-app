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
 * Feuille de synthèse mensuelle (ANNEXE XI).
 *
 * Colonnes :
 *   A  LIBELLE
 *   B  PAX/FRET TOTAL (a)         ← brut
 *   C  TOTAL GO-PASS/FRET IDEF    ← brut
 *   D  TOTAL EXONERE (a-b)        = B - C   ← formule Excel
 *
 * Lignes de données (9 à 15) :
 *   9   1. VOLS NATIONAUX (titre fusionné)
 *   10  a. PAX EMBARQUES
 *   11  b. FRET EMBARQUE
 *   12  2. VOLS INTERNATIONAUX (titre fusionné)
 *   13  a. PAX EMBARQUES
 *   14  b. FRET EMBARQUE
 *   15  c. FRET DEBARQUE
 *
 * La colonne D est entièrement calculée par Excel (=B-C).
 * Les valeurs B et C sont des données brutes issues du PHP.
 */
class SynthStatSheet implements WithTitle, ShouldAutoSize, FromArray, WithEvents
{
    protected string $sheetTitle;
    protected string $title;
    protected array  $domesticRows;
    protected array  $internationalRows;
    protected string $annexNumber;

    public function __construct(
        string $sheetTitle,
        string $title,
        array  $domesticRows,
        array  $internationalRows,
        string $annexNumber
    ) {
        $this->sheetTitle        = $sheetTitle;
        $this->title             = $title;
        $this->domesticRows      = $domesticRows;
        $this->internationalRows = $internationalRows;
        $this->annexNumber       = $annexNumber;
    }

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function array(): array
    {
        $cols = 4;
        $data = [];

        foreach ([
            ['SERVICE VTA'],
            ['BUREAU IDEF'],
            ["RVA AERO/N'DJILI"],
            ["DIVISION COMMERCIALE"],
            ['', 'ANNEXE XI'],
            [$this->title],
        ] as $line) {
            $data[] = array_pad($line, $cols, '');
        }

        // En-têtes (2 lignes, fusionnées dans AfterSheet)
        $data[] = ['LIBELLE', 'PAX /FRET TOTAL (a)', 'TOTAL GO-PASS/FRET IDEF ', 'TOTAL (PAX /FRET)'];
        $data[] = ['', '', 'FACTURE (b)', 'EXONERE(a-b)'];

        // Titre vols nationaux (ligne 9 — fusionnée dans AfterSheet)
        $data[] = ['1. VOLS NATIONAUX', '', '', ''];

        // PAX nationaux
        [$domPaxTotal, $domPaxIdef] = $this->getPaxRawTotals($this->domesticRows['pax'] ?? []);
        $data[] = ['a. PAX EMBARQUES', $domPaxTotal, $domPaxIdef, '']; // D ← formule

        // FRET national
        [$domFretTotal, $domFretIdef] = $this->getFretRawTotals(
            $this->domesticRows['fret']  ?? [],
            $this->domesticRows['exced'] ?? []
        );
        $data[] = ['b. FRET EMBARQUE', $domFretTotal, $domFretIdef, '']; // D ← formule

        // Titre vols internationaux (ligne 12 — fusionnée dans AfterSheet)
        $data[] = ['2. VOLS INTERNATIONAUX', '', '', ''];

        // PAX internationaux
        [$intPaxTotal, $intPaxIdef] = $this->getPaxRawTotals($this->internationalRows['pax'] ?? []);
        $data[] = ['a. PAX EMBARQUES', $intPaxTotal, $intPaxIdef, '']; // D ← formule

        // FRET international EMBARQUE
        $intDepFret  = $this->getFormattedFretOrExcedentdatas($this->internationalRows['fret']  ?? [])['departure'];
        $intDepExced = $this->getFormattedFretOrExcedentdatas($this->internationalRows['exced'] ?? [])['departure'];
        [$intDepTotal, $intDepIdef] = $this->getFretRawTotals($intDepFret, $intDepExced);
        $data[] = ['b. FRET EMBARQUE', $intDepTotal, $intDepIdef, '']; // D ← formule

        // FRET international DEBARQUE
        $intArrFret  = $this->getFormattedFretOrExcedentdatas($this->internationalRows['fret']  ?? [])['arrival'];
        $intArrExced = $this->getFormattedFretOrExcedentdatas($this->internationalRows['exced'] ?? [])['arrival'];
        [$intArrTotal, $intArrIdef] = $this->getFretRawTotals($intArrFret, $intArrExced);
        $data[] = ['c. FRET DEBARQUE', $intArrTotal, $intArrIdef, '']; // D ← formule

        $data[] = array_fill(0, $cols, '');

        $sig1            = array_fill(0, $cols, '');
        $sig1[$cols - 2] = 'LE CHEF DE BUREAU IDEF';
        $data[]          = $sig1;

        $sig2            = array_fill(0, $cols, '');
        $sig2[$cols - 2] = 'BANZE LUKUNGAY';
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
                $lastDataRow     = $highestRow - 3;

                // Lignes de données réelles (hors titres fusionnés)
                // Rows 10, 11, 13, 14, 15
                $dataRows = [10, 11, 13, 14, 15];

                // ── Formule D = B - C pour chaque ligne de données ────────
                foreach ($dataRows as $row) {
                    $s->getCell("D{$row}")->setValue("=B{$row}-C{$row}");
                    $s->setCellValueExplicit("B{$row}", $s->getCell("B{$row}")->getValue(), DataType::TYPE_NUMERIC);
                    $s->setCellValueExplicit("C{$row}", $s->getCell("C{$row}")->getValue(), DataType::TYPE_NUMERIC);
                }

                // ── Styles titres ─────────────────────────────────────────
                for ($row = 1; $row <= 4; $row++) {
                    $s->mergeCells("A{$row}:{$highestCol}{$row}");
                    $s->getStyle("A{$row}")->getFont()->setBold(false)->setSize(12);
                    $s->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                }
                $s->getStyle("B5")->getFont()->setBold(false)->setSize(14);

                $s->mergeCells("A6:{$highestCol}6");
                $s->getStyle("A6")->getFont()->setBold(true)->setSize(16);
                $s->getStyle("A6")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->getStyle("A6")->getFill()->setFillType('solid')
                    ->getStartColor()->setARGB('FFD9E1F2');

                $headerRow = 7;
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

                // ── Fusions titres de section ─────────────────────────────
                $s->mergeCells("A9:{$highestCol}9");
                $s->mergeCells("A12:{$highestCol}12");
                $s->getStyle("A9")->getFont()->setBold(true);
                $s->getStyle("A12")->getFont()->setBold(true);

                // ── Style données ─────────────────────────────────────────
                $s->getStyle("A9:{$highestCol}15")->getFont()->setSize(14);
                $s->getStyle("B10:{$highestCol}15")->getNumberFormat()->setFormatCode('#,##0');
                $s->getStyle("{$highestCol}10:{$highestCol}15")->getFont()->setBold(true);

                $s->getRowDimension($headerRow)->setRowHeight(25);
                for ($row = 8; $row <= $lastDataRow; $row++) {
                    $s->getRowDimension($row)->setRowHeight(20);
                }

                // ── Signature ─────────────────────────────────────────────
                $sigRow1  = $lastDataRow + 2;
                $sigRow2  = $sigRow1 + 1;
                $sigEnd   = Coordinate::stringFromColumnIndex($highestColIndex);

                $s->mergeCells("C{$sigRow1}:{$sigEnd}{$sigRow1}");
                $s->getStyle("C{$sigRow1}")->getFont()->setBold(true)->setSize(11);
                $s->getStyle("C{$sigRow1}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $s->mergeCells("C{$sigRow2}:{$sigEnd}{$sigRow2}");
                $s->getStyle("C{$sigRow2}")->getFont()->setBold(true)->setSize(12);
                $s->getStyle("C{$sigRow2}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }

    // ─── Helpers : retournent [total_trafic, total_idef] ─────────────────────

    private function getPaxRawTotals(array $paxDatas): array
    {
        $trafficPax = 0;
        $idefPax    = 0;
        foreach ($paxDatas as $dayValues) {
            $copy = $dayValues;
            array_shift($copy);
            foreach ($copy as $value) {
                $trafficPax += (int) $value['trafic'];
                $idefPax    += (int) $value['gopass'];
            }
        }
        return [$trafficPax, $idefPax];
    }

    private function getFretRawTotals(array $fretDatas, array $excedentDatas): array
    {
        $trafficFret = 0;
        $idefFret    = 0;
        foreach (array_merge($fretDatas, $excedentDatas) as $dayValues) {
            $copy = $dayValues;
            array_shift($copy);
            foreach ($copy as $key => $value) {
                $trafficFret += (int) $value;
                if ($key !== 'UN') $idefFret += (int) $value;
            }
        }
        return [$trafficFret, $idefFret];
    }

    private function getFormattedFretOrExcedentdatas(array $dataRows): array
    {
        $departureDatas = [];
        $arrivalDatas   = [];
        foreach ($dataRows as $rows) {
            $date              = isset($rows['DATE']) ? ['DATE' => $rows['DATE']] : ['MOIS' => $rows['MOIS']];
            $daylyDep          = [];
            $daylyArr          = [];
            foreach ($rows as $key => $value) {
                if ($key === 'DATE' || $key === 'MOIS') continue;
                if (isset($value['departure'])) $daylyDep[$key] = $value['departure'];
                if (isset($value['arrival']))   $daylyArr[$key] = $value['arrival'];
            }
            $departureDatas[] = array_merge($date, $daylyDep);
            $arrivalDatas[]   = array_merge($date, $daylyArr);
        }
        return ['departure' => $departureDatas, 'arrival' => $arrivalDatas];
    }
}