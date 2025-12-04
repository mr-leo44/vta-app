<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TraficReportAnnualExport implements WithMultipleSheets
{
    protected $regime;
    protected $year;
    protected $reportData; // contient: pax, fret_depart, fret_arrivee, exced_depart, exced_arrivee + operators

    public function __construct($regime, $year, $reportData)
    {
        $this->regime     = $regime;
        $this->year       = $year;
        $this->reportData = $reportData;
    }

    public function sheets(): array
    {
        $regimeLabel   = $this->regime === "domestic" ? 'NATIONAL' : 'INTERNATIONAL';
        $short_regime  = $regimeLabel === 'NATIONAL' ? 'NAT' : 'INT';
        $year          = $this->year;

        // 5 feuilles en INTERNATIONAL
        if ($this->regime === "international") {
            return [
                new TraficStatAnnualSheet(
                    "PAX $short_regime",
                    "PASSAGERS VOLS $regimeLabel ANNÉE $year",
                    $this->reportData['pax'],
                    $this->reportData['operators']['pax']
                ),
                new TraficStatAnnualSheet(
                    "FRET $short_regime DEPART",
                    "FRET $regimeLabel DEPART ANNÉE $year",
                    $this->reportData['fret_depart'],
                    $this->reportData['operators']['fret']
                ),
                new TraficStatAnnualSheet(
                    "FRET $short_regime ARRIVEE",
                    "FRET $regimeLabel ARRIVEE ANNÉE $year",
                    $this->reportData['fret_arrivee'],
                    $this->reportData['operators']['fret']
                ),
                new TraficStatAnnualSheet(
                    "EXCED FRET $short_regime DEPART",
                    "EXCEDENT FRET $regimeLabel DEPART ANNÉE $year",
                    $this->reportData['exced_depart'],
                    $this->reportData['operators']['fret']
                ),
                new TraficStatAnnualSheet(
                    "EXCED FRET $short_regime ARRIVEE",
                    "EXCEDENT FRET $regimeLabel ARRIVEE ANNÉE $year",
                    $this->reportData['exced_arrivee'],
                    $this->reportData['operators']['fret']
                )
            ];
        }

        // 3 feuilles en NATIONAL
        return [
            new TraficStatAnnualSheet(
                "PAX $short_regime",
                "PASSAGERS VOLS $regimeLabel ANNÉE $year",
                $this->reportData['pax'],
                $this->reportData['operators']['pax']
            ),
            new TraficStatAnnualSheet(
                "FRET $short_regime DEPART",
                "FRET $regimeLabel DEPART ANNÉE $year",
                $this->reportData['fret_depart'],
                $this->reportData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                "EXCED FRET $short_regime DEPART",
                "EXCEDENT FRET $regimeLabel DEPART ANNÉE $year",
                $this->reportData['exced_depart'],
                $this->reportData['operators']['fret']
            )
        ];
    }
}
