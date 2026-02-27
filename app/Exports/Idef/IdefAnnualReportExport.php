<?php

namespace App\Exports\Idef;

use App\Exports\Idef\AnnualDomesticFreightStatSheet;
use App\Exports\Idef\AnnualEcartStatSheet;
use App\Exports\Idef\AnnualInternationalFreightStatSheet;
use App\Exports\Idef\ExonerationAnnualStatSheet;
use App\Exports\Idef\PAXAnnualStatSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IdefAnnualReportExport implements WithMultipleSheets
{

    protected $year;

    protected $internationaldata;

    protected $domesticData;

    public function __construct($year, $internationaldata, $domesticData)
    {
        $this->year = $year;
        $this->internationaldata = $internationaldata;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $year = $this->year;
        return [
            new PAXAnnualStatSheet(
                'PAX NAT',
                "STATISTIQUE GO PASS RAMASSES NATIONAL ANNUEL $year",
                $this->domesticData['pax'],
                $this->domesticData['operators']['pax']
            ),
            new PAXAnnualStatSheet(
                'PAX INTER',
                "STATISTIQUE GO PASS RAMASSES INTERNATIONAL ANNUEL $year",
                $this->internationaldata['pax'],
                $this->internationaldata['operators']['pax']
            ),
            new AnnualEcartStatSheet(
                'ECART NAT',
                "SITUATION  PASSAGERS EMBARQUES ET  GO-PASS  RAMASSES NATIONAL ANNUEL $year",
                $this->domesticData['pax']
            ),
            new AnnualEcartStatSheet(
                'ECART INT',
                "SITUATION  PASSAGERS EMBARQUES ET  GO-PASS  RAMASSES INTERNATIONAL ANNUEL $year",
                $this->internationaldata['pax']
            ),
            new AnnualDomesticFreightStatSheet(
                'FRET NAT',
                "STATISTIQUES ANNUELS DES FRETS EMBARQUES ET FRETS IDEF",
                "VOLS NATIONAUX $year",
                $this->domesticData['fret'],
                $this->domesticData['operators']['fret'],
                "ANNEXE V"
            ),
            new AnnualDomesticFreightStatSheet(
                'EXCED FRET NAT',
                "STATISTIQUES ANNUELLES DES EXCEDANTS BAGAGES FRETS EMBARQUES ET FRETS IDEF",
                "VOLS NATIONAUX $year",
                $this->domesticData['exced'],
                $this->domesticData['operators']['fret'],
                "ANNEXE VI"
            ),
            new ExonerationAnnualStatSheet(
                'FRET EXON NAT',
                "STATISTIQUES ANNUELLES DES FRETS EXONERES PAR CATEGORIE D'EXONERATION VOLS NATIONAUX EMBARQUES $year",
                $this->domesticData['fret'],
                $this->domesticData['operators']['fret'],
                "ANNEXE VII"
            ),
            new AnnualInternationalFreightStatSheet(
                'FRET INTER',
                "STATISTIQUES ANNUELLES DES FRETS DEBARQUES/EMBARQUES ET FRETS IDEF",
                "VOLS INTERNATIONAUX $year",
                $this->internationaldata['fret'],
                $this->internationaldata['operators']['fret'],
                "ANNEXE VIII"
            ),
            new AnnualInternationalFreightStatSheet(
                'EXCED FRET INT DEP ARR',
                "STATISTIQUES ANNUELLES EXCEDANTS BAGAGES FRETS DEBARQUES/EMBARQUES ET FRETS IDEF",
                "VOLS INTERNATIONAUX $year",
                $this->internationaldata['exced'],
                $this->internationaldata['operators']['fret'],
                "ANNEXE X"
            ),
            new ExonerationAnnualStatSheet(
                'FRET EXON INT',
                "STATISTIQUES ANNUELLES DES FRETS EXONERES PAR CATEGORIE D'EXONERATION VOLS INTERNATIONAUX EMBARQUES/DEBARQUES $year",
                $this->internationaldata['fret'],
                $this->internationaldata['operators']['fret'],
                "ANNEXE IX"
            ),
            new SynthStatSheet(
                'SYNTH',
                "SYNTHESE STATISTIQUES ANNUELLES IDEF $year",
                $this->domesticData,
                $this->internationaldata,
                "ANNEXE XI"
            )
        ];
    }
}
