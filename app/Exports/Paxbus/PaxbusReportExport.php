<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Paxbus\PaxbusInternationalStatSheet;
use App\Exports\Paxbus\PaxbusDomesticStatSheet;
use App\Exports\Paxbus\PaxbusSyntheticStatSheet;

class PaxbusReportExport implements WithMultipleSheets
{
    protected $month;
    protected $year;
    protected $internationalData;
    protected $domesticData;

    public function __construct($month, $year, $internationalData, $domesticData)
    {
        $this->month = $month;
        $this->year = $year;
        $this->internationalData = $internationalData;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $year = $this->year;
        $selectedMonth = $this->getMonth($this->month);

        return [
            new PaxbusInternationalStatSheet(
                "STATISTIQUE INT",
                "STATISTIQUE DES PASSAGERS BUS ROTATION INTERNATIONAL $selectedMonth $year",
                $this->internationalData,
                $this->internationalData['operators']
            ),
            new PaxbusSyntheticStatSheet(
                "TAB SYNT",
                "RAPPORT MENSUEL PAX BUS $selectedMonth $year",
                $this->domesticData,
                $this->internationalData
            ),
            new PaxbusDomesticStatSheet(
                "VOL NAT",
                "BASE DE FACTURATION PRESTATION BUS TARMAC VOL NATIONAUX DEPART $selectedMonth $year",
                $this->domesticData,
                $this->domesticData['operators']
            ),
        ];
    }

    private function getMonth(string $month): string
    {
        $mois = $month;

        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        $prefixe = "";
        foreach($voyelles as $voyelle) {
            if (str_starts_with($mois, $voyelle)) {
                $prefixe = "D'";
                break;
            }
        }
        if($prefixe == "") {
            $prefixe = "DE";
        }

        return "MOIS " . $prefixe . $mois;
    }
}