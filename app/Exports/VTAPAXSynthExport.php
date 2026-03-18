<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Monthly PAX synthesis export — by operators.
 * 4 sheets:
 *   NAT COM    — Domestic commercial operators
 *   NAT NON-COM — Domestic non-commercial operators
 *   INT COM    — International commercial operators
 *   INT NON-COM — International non-commercial operators
 */
class VTAPAXSynthExport implements WithMultipleSheets
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

        $domCom    = $this->domesticData['commercial']     ?? [];
        $domNonCom = $this->domesticData['non_commercial'] ?? [];
        $intCom    = $this->internationalData['commercial']     ?? [];
        $intNonCom = $this->internationalData['non_commercial'] ?? [];

        return [
            new VTAPAXSynthSheet(
                'NAT COM',
                "SYNTHÈSE FRET NATIONAL — COMMERCIAUX — {$period}",
                $domCom['fret']      ?? [],
                $domCom['excedents'] ?? [],
                false
            ),
            new VTAPAXSynthSheet(
                'NAT NON-COM',
                "SYNTHÈSE FRET NATIONAL — NON COMMERCIAUX — {$period}",
                $domNonCom['fret']      ?? [],
                $domNonCom['excedents'] ?? [],
                false
            ),
            new VTAPAXSynthSheet(
                'INT COM',
                "SYNTHÈSE FRET INTERNATIONAL — COMMERCIAUX — {$period}",
                $intCom['fret_depart']   ?? [],
                $intCom['exced_depart']  ?? [],
                true,
                $intCom['fret_arrivee']  ?? [],
                $intCom['exced_arrivee'] ?? []
            ),
            new VTAPAXSynthSheet(
                'INT NON-COM',
                "SYNTHÈSE FRET INTERNATIONAL — NON COMMERCIAUX — {$period}",
                $intNonCom['fret_depart']   ?? [],
                $intNonCom['exced_depart']  ?? [],
                true,
                $intNonCom['fret_arrivee']  ?? [],
                $intNonCom['exced_arrivee'] ?? []
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