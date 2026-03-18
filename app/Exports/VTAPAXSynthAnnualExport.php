<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Annual PAX synthesis export — by operators.
 * 4 sheets (same structure as monthly, annual granularity).
 */
class VTAPAXSynthAnnualExport implements WithMultipleSheets
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
            new VTAPAXSynthSheet(
                'NATIONAL',
                "SYNTHÈSE PAX NATIONAL — COMMERCIAUX — {$period}",
                $domCom['pax'] ?? [],
            ),
            new VTAPAXSynthSheet(
                'INTERNATIONAL',
                "SYNTHÈSE PAX INTERNATIONAL — COMMERCIAUX — {$period}",
                $intCom['pax'] ?? [],
            ),
        ];
    }
}