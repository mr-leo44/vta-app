<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Annual freight synthesis export — by operators.
 * 4 sheets (same structure as monthly, annual granularity).
 */
class VTAFreightSynthAnnualExport implements WithMultipleSheets
{
    public function __construct(
        protected int   $year,
        protected array $domesticData,
        protected array $internationalData
    ) {}

    public function sheets(): array
    {
        $period = (string) $this->year;

        $domCom    = $this->domesticData['commercial']     ?? [];
        $domNonCom = $this->domesticData['non_commercial'] ?? [];
        $intCom    = $this->internationalData['commercial']     ?? [];
        $intNonCom = $this->internationalData['non_commercial'] ?? [];

        return [
            new VTAFreightSynthSheet(
                'NAT COM',
                "SYNTHÈSE FRET NATIONAL — COMMERCIAUX — {$period}",
                $domCom['fret']      ?? [],
                $domCom['excedents'] ?? [],
                false
            ),
            new VTAFreightSynthSheet(
                'NAT NON-COM',
                "SYNTHÈSE FRET NATIONAL — NON COMMERCIAUX — {$period}",
                $domNonCom['fret']      ?? [],
                $domNonCom['excedents'] ?? [],
                false
            ),
            new VTAFreightSynthSheet(
                'INT COM',
                "SYNTHÈSE FRET INTERNATIONAL — COMMERCIAUX — {$period}",
                $intCom['fret_depart']   ?? [],
                $intCom['exced_depart']  ?? [],
                true,
                $intCom['fret_arrivee']  ?? [],
                $intCom['exced_arrivee'] ?? []
            ),
            new VTAFreightSynthSheet(
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
}