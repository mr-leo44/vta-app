<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PaxbusWeeklyReportExport implements WithMultipleSheets
{
    protected $quinzaine;
    protected $month;
    protected $year;
    protected $internationalData;
    protected $domesticData;

    public function __construct(
        string $quinzaine,
        string $month,
        int $year,
        array $internationalData,
        array $domesticData
    ) {
        $this->quinzaine = $quinzaine;
        $this->month = $month;
        $this->year = $year;
        $this->internationalData = $internationalData;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $title = "RAPPORT HEBDOMADAIRE VERIFICATION PAX BUS ET EVALUATION EN DOLLARS {$this->month} {$this->year}";
        
        $startDate = $this->internationalData['startDate'] ?? '';
        $endDate = $this->internationalData['endDate'] ?? '';
        
        $subTitleInter = "AMERICAINS/INTERNATIONAL DU {$startDate} AU {$endDate}";
        $subTitleDom = "NATIONAL DU {$startDate} AU {$endDate}";

        return [
            new PaxbusWeeklyInternationalSheet(
                "INTERNATIONAL",
                $title,
                $subTitleInter,
                $this->internationalData
            ),
            new PaxbusWeeklyDomesticSheet(
                "NATIONAL",
                $title,
                $subTitleDom,
                $this->domesticData
            ),
        ];
    }
}