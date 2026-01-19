<?php

namespace App\Exports\Traffic;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TraficReportExport implements WithMultipleSheets
{
    protected $month;

    protected $year;

    protected $internationaldata;

    protected $domesticData;

    public function __construct($month, $year, $internationaldata, $domesticData)
    {
        $this->month = $month;
        $this->year = $year;
        $this->internationaldata = $internationaldata;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $selectedMonth = $this->getMonth($this->month);
        $year = $this->year;

        return [
            new TraficStatSheet(
                'PAX NAT',
                "PASSAGERS VOLS NATIONAL $selectedMonth $year",
                $this->domesticData['pax'],
                $this->domesticData['operators']['pax']
            ),
            new TraficStatSheet(
                'PAX INT',
                "PASSAGERS VOLS INTERNATIONAL $selectedMonth $year",
                $this->internationaldata['pax'],
                $this->internationaldata['operators']['pax']
            ),
            new TraficStatSheet(
                'FRET NAT DEPART',
                "FRET NATIONAL DEPART $selectedMonth $year",
                $this->domesticData['fret_depart'],
                $this->domesticData['operators']['fret']
            ),
            new TraficStatSheet(
                'EXCED FRET NAT DEPART',
                "EXCEDENT FRET NATIONAL DEPART $selectedMonth $year",
                $this->domesticData['exced_depart'],
                $this->domesticData['operators']['fret']
            ),
            new TraficStatSheet(
                'FRET INT DEPART',
                "FRET INTERNATIONAL DEPART $selectedMonth $year",
                $this->internationaldata['fret_depart'],
                $this->internationaldata['operators']['fret']
            ),
            new TraficStatSheet(
                'EXCED FRET INT DEPART',
                "EXCEDENT FRET INTERNATIONAL DEPART $selectedMonth $year",
                $this->internationaldata['exced_depart'],
                $this->internationaldata['operators']['fret']
            ),
            new TraficStatSheet(
                'FRET INT ARRIVEE',
                "FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
                $this->internationaldata['fret_arrivee'],
                $this->internationaldata['operators']['fret']
            ),
            new TraficStatSheet(
                'EXCED FRET INT ARRIVEE',
                "EXCEDENT FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
                $this->internationaldata['exced_arrivee'],
                $this->internationaldata['operators']['fret']
            ),
        ];

        // if ($this->regime === "international") {
        //     return [
        //

        //     ];
        // }

        // return [
        //
        //
        // ];
    }

    private function getMonth(string $month): string
    {
        $mois = $month;

        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        $prefixe = '';
        foreach ($voyelles as $voyelle) {
            if (str_starts_with($mois, $voyelle)) {
                $prefixe = "D'";
                break;
            }
        }
        if ($prefixe == '') {
            $prefixe = 'DE ';
        }

        return 'MOIS '.$prefixe.$mois;
    }
}
