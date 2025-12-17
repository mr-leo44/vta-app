<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Paxbus\PaxbusInternationalStatSheet;
use App\Exports\Paxbus\PaxbusDomesticStatSheet;

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

        return [
            new PaxbusInternationalStatSheet(
                "STATISTIQUE INT",
                "STATISTIQUE DES PASSAGERS BUS ROTATION INTERNATIONAL MOIS DE $this->month $year",
                $this->internationalData,
                $this->internationalData['operators']
            ),
            new PaxbusDomesticStatSheet(
                "VOL NAT",
                "BASE DE FACTURATION PRESTATION BUS TARMAC VOL NATIONAUX DEPART $this->month  $year",
                $this->domesticData,
                $this->domesticData['operators']
            ),
        ];
    }

    private function getMonth(): string
    {
        $monthNames = [
            '01' => 'JANVIER',
            '02' => 'FÉVRIER',
            '03' => 'MARS',
            '04' => 'AVRIL',
            '05' => 'MAI',
            '06' => 'JUIN',
            '07' => 'JUILLET',
            '08' => 'AOÛT',
            '09' => 'SEPTEMBRE',
            '10' => 'OCTOBRE',
            '11' => 'NOVEMBRE',
            '12' => 'DÉCEMBRE'
        ];

        $monthNumber = str_pad((string)$this->month, 2, '0', STR_PAD_LEFT);
        $mois = $monthNames[$monthNumber] ?? 'MOIS INCONNU';

        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        $prefixe = in_array($mois[0], $voyelles) ? "D'" : "DE ";

        return "MOIS " . $prefixe . $mois;
    }
}