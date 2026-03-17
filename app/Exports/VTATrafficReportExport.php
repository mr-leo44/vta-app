<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Monthly traffic report export.
 * 2 sheets: Domestic | International
 */
class VTATrafficReportExport implements WithMultipleSheets
{
    public function __construct(
        protected string $monthName,
        protected int    $year,
        protected array  $domesticData,
        protected array  $internationalData
    ) {}

    public function sheets(): array
    {
        $period = $this->getMonthLabel($this->monthName) . ' ' . $this->year;

        return [
            new VTATrafficReportSheet(
                'TRAFIC NAT',
                "STATISTIQUES MENSUELLES DU TRAFIC NATIONAL — {$period}",
                $this->domesticData['pax'],
                $this->domesticData['fret'],
                $this->domesticData['excedents'],
                false,
                [],
                [],
                false
            ),
            new VTATrafficReportSheet(
                'TRAFIC INT',
                "STATISTIQUES MENSUELLES DU TRAFIC INTERNATIONAL — {$period}",
                $this->internationalData['pax'],
                $this->internationalData['fret_depart'],
                $this->internationalData['exced_depart'],
                true,
                $this->internationalData['fret_arrivee'],
                $this->internationalData['exced_arrivee'],
                false
            ),
        ];
    }

    private function getMonthLabel(string $month): string
    {
        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        foreach ($voyelles as $v) {
            if (str_starts_with($month, $v)) {
                return "MOIS D'" . $month;
            }
        }
        return 'MOIS DE ' . $month;
    }
}