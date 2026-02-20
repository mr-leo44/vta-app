<?php

namespace App\Exports\Idef;

use App\Exports\Idef\DomesticFreightStatSheet;
use App\Exports\Idef\EcartStatSheet;
use App\Exports\Idef\InternationalFreightStatSheet;
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
            new DomesticFreightStatSheet(
                'FRET NAT',
                "STATISTIQUES MENSUELLES FRETS  EMBARQUES ET FRETS IDEF",
                "EMBARQUES VOLS NATIONAUX $selectedMonth $year",
                $this->domesticData['fret'],
                $this->domesticData['operators']['fret'],
                "ANNEXE V"
            ),
            new DomesticFreightStatSheet(
                'EXCED FRET NAT',
                "STATISTIQUES MENSUELLES EXCEDANT BAGAGE FRETS  EMBARQUES ET FRETS IDEF",
                "EMBARQUES VOLS NATIONAUX $selectedMonth $year",
                $this->domesticData['exced'],
                $this->domesticData['operators']['fret'],
                "ANNEXE VI"
            ),
            // new TraficStatSheet(
            //     'FRET EXON NAT',
            //     "EXCEDENT FRET INTERNATIONAL ARRIVEE $selectedMonth $year",
            //     $this->internationaldata['exced_arrivee'],
            //     $this->internationaldata['operators']['fret']
            // ),
            new InternationalFreightStatSheet(
                'FRET INTER',
                "STATISTIQUES MENSUELLES FRETS DEBARQUES/EMBARQUES ET FRETS IDEF",
                "DEBARQUES/EMBARQUES VOLS INTERNATIONAUX $selectedMonth $year",
                $this->internationaldata['fret'],
                $this->internationaldata['operators']['fret'],
                "ANNEXE VIII"
            ),
            new InternationalFreightStatSheet(
                'EXCED FRET INT DEP ARR',
                "STATISTIQUES MENSUELLES EXCEDANT BAGAGE FRETS  EMBARQUES ET FRETS IDEF",
                "EMBARQUES VOLS INTERNATIONAUX $selectedMonth $year",
                $this->internationaldata['exced'],
                $this->internationaldata['operators']['fret'],
                "ANNEXE IX"
            ),
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
                $this->internationaldata,
                "ANNEXE XI"
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
