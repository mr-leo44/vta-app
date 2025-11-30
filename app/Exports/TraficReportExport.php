<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TraficReportExport implements WithMultipleSheets
{
    protected $regime;
    protected $month;
    protected $year;
    protected $reportData;

    public function __construct($regime, $month, $year, $reportData)
    {
        $this->regime = $regime;
        $this->month = $month;
        $this->year = $year;
        $this->reportData = $reportData;
    }

    public function sheets(): array
    {
        $regime = strtoupper($this->regime);
        $selectedMonth = $this->getMonth();
        $year = $this->year;
        $short_regime = $regime === 'DOMESTIC' ? 'NAT' : 'INT';
        
        if ($this->regime === "international") {
            return [
                new TraficStatSheet(
                    "PAX $short_regime",
                    "PASSAGERS VOLS $regime $selectedMonth $year",
                    $this->reportData['pax'],
                    $this->reportData['operators']
                ),
                new TraficStatSheet(
                    "FRET $short_regime DEPART",
                    "FRET $regime DEPART $selectedMonth $year",
                    $this->reportData['fret_depart'],
                    $this->reportData['operators']
                ),
                new TraficStatSheet(
                    "FRET $short_regime ARRIVEE",
                    "FRET $regime ARRIVEE $selectedMonth $year",
                    $this->reportData['fret_arrivee'],
                    $this->reportData['operators']
                ),
                new TraficStatSheet(
                    "EXCED FRET $short_regime DEPART",
                    "EXCEDENT FRET $regime DEPART $selectedMonth $year",
                    $this->reportData['exced_depart'],
                    $this->reportData['operators']
                ),
                new TraficStatSheet(
                    "EXCED FRET $short_regime ARRIVEE",
                    "EXCEDENT FRET $regime ARRIVEE $selectedMonth $year",
                    $this->reportData['exced_arrivee'],
                    $this->reportData['operators']
                )
            ];
        }
        
        return [
            new TraficStatSheet(
                "PAX $short_regime",
                "PASSAGERS VOLS $regime $selectedMonth $year",
                $this->reportData['pax'],
                $this->reportData['operators']
            ),
            new TraficStatSheet(
                "FRET $short_regime DEPART",
                "FRET $regime DEPART $selectedMonth $year",
                $this->reportData['fret_depart'],
                $this->reportData['operators']
            ),
            new TraficStatSheet(
                "EXCED FRET $short_regime DEPART",
                "EXCEDENT FRET $regime DEPART $selectedMonth $year",
                $this->reportData['exced_depart'],
                $this->reportData['operators']
            )
        ];
    }

    private function getMonth(): string
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
            '12' => 'DÉCEMBRE'
        ];

        $monthNumber = str_pad((string)$this->month, 2, '0', STR_PAD_LEFT);
        $mois = $monthNames[$monthNumber] ?? 'MOIS INCONNU';

        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        $prefixe = in_array($mois[0], $voyelles) ? "D'" : "DE ";

        return "MOIS " . $prefixe . $mois;
    }
}