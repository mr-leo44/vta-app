<?php

namespace App\Exports\Traffic;

use App\Exports\Traffic\TraficStatAnnualSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TraficReportAnnualExport implements WithMultipleSheets
{
    protected $year;

    protected $internationalData;

    protected $domesticData;

    public function __construct($year, $internationalData, $domesticData)
    {
        $this->year = $year;
        $this->internationalData = $internationalData;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $year = $this->year;

        return [
            new TraficStatAnnualSheet(
                'PAX NAT',
                "PASSAGERS VOLS NATIONAUX ANNÉE $year",
                $this->domesticData['pax'],
                $this->domesticData['operators']['pax']
            ),
            new TraficStatAnnualSheet(
                'PAX INT',
                "PASSAGERS VOLS INTERNATIONAUX ANNÉE $year",
                $this->internationalData['pax'],
                $this->internationalData['operators']['pax']
            ),
            new TraficStatAnnualSheet(
                'FRET NAT DEPART',
                "FRET DEPART VOLS NATIONAUX ANNÉE $year",
                $this->domesticData['fret_depart'],
                $this->domesticData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                'EXCED FRET NAT DEPART',
                "EXCEDENT FRET DEPART VOLS NATIONAUX ANNÉE $year",
                $this->domesticData['exced_depart'],
                $this->domesticData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                'FRET INT DEPART',
                "FRET DEPART VOLS INTERNATIONAUX ANNÉE $year",
                $this->internationalData['fret_depart'],
                $this->internationalData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                'EXCED FRET INT DEPART',
                "EXCEDENT FRET DEPART VOLS INTERNATIONAUX ANNÉE $year",
                $this->internationalData['exced_depart'],
                $this->internationalData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                'FRET INT ARRIVEE',
                "FRET ARRIVEE VOLS INTERNATIONAUX ANNÉE $year",
                $this->internationalData['fret_arrivee'],
                $this->internationalData['operators']['fret']
            ),
            new TraficStatAnnualSheet(
                'EXCED FRET INT ARRIVEE',
                "EXCEDENT FRET ARRIVEE VOLS INTERNATIONAUX ANNÉE $year",
                $this->internationalData['exced_arrivee'],
                $this->internationalData['operators']['fret']
            ),
        ];
    }
}
