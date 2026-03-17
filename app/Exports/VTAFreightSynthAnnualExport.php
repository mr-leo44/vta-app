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
        $intCom    = $this->internationalData['commercial']     ?? [];

        return [
            new VTAFreightSynthSheet(
                'NATIONAL',
                "SYNTHÈSE FRET NATIONAL — COMMERCIAUX — {$period}",
                $domCom['fret']      ?? [],
                $domCom['excedents'] ?? [],
                false
            ),
            new VTAFreightSynthSheet(
                'INTERNATIONAL',
                "SYNTHÈSE FRET INTERNATIONAL — COMMERCIAUX — {$period}",
                $intCom['fret_depart']   ?? [],
                $intCom['exced_depart']  ?? [],
                true,
                $intCom['fret_arrivee']  ?? [],
                $intCom['exced_arrivee'] ?? []
            ),
        ];
    }
}