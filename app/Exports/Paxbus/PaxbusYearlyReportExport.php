<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Paxbus\PaxbusYearlyInternationalOperatorsStatSheet;

class PaxbusYearlyReportExport implements WithMultipleSheets
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
        // $selectedMonth = $this->getMonth($this->month);
        return [
            new PaxbusYearlyInternationalOperatorsStatSheet(
                "CIES INT",
                "RAPPORT ANNUEL PAX BUS $year",
                "COMPAGNIES INTERNATIONALES",
                $this->internationalData,
                $this->internationalData['operators']
            ),
            
        ];
    }
}