<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Annual traffic report export.
 * 2 sheets: Domestic | International
 */
class VTATrafficAnnualReportExport implements WithMultipleSheets
{
    public function __construct(
        protected int   $year,
        protected array $domesticData,
        protected array $internationalData
    ) {}

    public function sheets(): array
    {
        return [
            new VTATrafficReportSheet(
                'TRAFIC NAT',
                "EVOLUTION DU TRAFIC AERO NATIONAL — {$this->year}",
                $this->domesticData['pax'],
                $this->domesticData['fret'],
                $this->domesticData['excedents'],
                false,
                [],
                [],
                true
            ),
            new VTATrafficReportSheet(
                'TRAFIC INT',
                "EVOLUTION DU TRAFIC AERO INTERNATIONAL — {$this->year}",
                $this->internationalData['pax'],
                $this->internationalData['fret_depart'],
                $this->internationalData['exced_depart'],
                true,
                $this->internationalData['fret_arrivee'],
                $this->internationalData['exced_arrivee'],
                true
            ),
        ];
    }
}