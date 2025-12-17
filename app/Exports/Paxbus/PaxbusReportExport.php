<?php

namespace App\Exports\Paxbus;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Paxbus\PaxbusInternationalStatSheet;

class PaxbusReportExport implements WithMultipleSheets
{
    protected $month;
    protected $year;
    protected $reportData;

    public function __construct($month, $year, $reportData)
    {
        $this->month = $month;
        $this->year = $year;
        $this->reportData = $reportData;
    }

    public function sheets(): array
    {
        $selectedMonth = $this->getMonth();
        $year = $this->year;

        return [
            new PaxbusInternationalStatSheet(
                "STATISTIQUE INT",
                "STATISTIQUE DES PASSAGERS BUS ROTATION INTERNATIONAL MOIS DE $selectedMonth $year",
                $this->reportData,
                $this->reportData['operators']
            )
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
