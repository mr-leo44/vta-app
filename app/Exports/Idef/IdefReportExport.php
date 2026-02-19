<?php

namespace App\Exports\Idef;

use App\Exports\Idef\EcartStatSheet;
use App\Exports\Idef\PAXStatSheet;
use App\Exports\Idef\SynthStatSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IdefReportExport implements WithMultipleSheets
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
            new PAXStatSheet(
                'PAX NAT',
                "STATISTIQUE GO PASS RAMASSES NATIONAL $selectedMonth $year",
                $this->domesticData['pax'],
                $this->domesticData['operators']['pax']
            ),
            new PAXStatSheet(
                'PAX INTER',
                "STATISTIQUE GO PASS RAMASSES INTERNATIONAL $selectedMonth $year",
                $this->internationaldata['pax'],
                $this->internationaldata['operators']['pax']
            ),
            new EcartStatSheet(
                'ECART NAT',
                "SITUATION  PASSAGERS EMBARQUES ET  GO-PASS  RAMASSES NATIONAL $selectedMonth $year",
                $this->domesticData['pax']
            ),
            new EcartStatSheet(
                'ECART INT',
                "SITUATION  PASSAGERS EMBARQUES ET  GO-PASS  RAMASSES INTERNATIONAL $selectedMonth $year",
                $this->internationaldata['pax']
            ),
            // new TraficStatSheet(
            //     'FRET NAT',
            //     "EXCEDENT FRET INTERNATIONAL DEPART $selectedMonth $year",
            //     $this->internationaldata['exced_depart'],
            //     $this->internationaldata['operators']['fret']
            // ),
            // new TraficStatSheet(
            //     'EXCED FRET NAT',
            //     "FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
            //     $this->internationaldata['fret_arrivee'],
            //     $this->internationaldata['operators']['fret']
            // ),
            // new TraficStatSheet(
            //     'FRET EXON NAT',
            //     "EXCEDENT FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
            //     $this->internationaldata['exced_arrivee'],
            //     $this->internationaldata['operators']['fret']
            // ),
            // new TraficStatSheet(
            //     'FRET INT',
            //     "EXCEDENT FRET INTERNATIONAL DEPART $selectedMonth $year",
            //     $this->internationaldata['exced_depart'],
            //     $this->internationaldata['operators']['fret']
            // ),
            // new TraficStatSheet(
            //     'EXCED FRET INT',
            //     "FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
            //     $this->internationaldata['fret_arrivee'],
            //     $this->internationaldata['operators']['fret']
            // ),
            // new TraficStatSheet(
            //     'FRET EXON INT',
            //     "EXCEDENT FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
            //     $this->internationaldata['exced_arrivee'],
            //     $this->internationaldata['operators']['fret']
            // ),
            new SynthStatSheet(
                'SYNTH',
                "SYNTHESE STATISTIQUES MENSUELLES IDEF $selectedMonth $year",
                $this->domesticData,
                $this->internationaldata
            ),
        ];
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

        return 'MOIS ' . $prefixe . $mois;
    }
}
