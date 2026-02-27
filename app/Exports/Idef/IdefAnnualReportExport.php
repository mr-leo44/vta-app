<?php

namespace App\Exports\Idef;

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
        ];
    }
}
