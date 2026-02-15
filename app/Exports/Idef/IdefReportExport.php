<?php

namespace App\Exports\Idef;

use App\Exports\Idef\SynthStatSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class IdefReportExport implements WithMultipleSheets
{
    protected $month;

    protected $year;

    protected $internationaldata;

    protected $domesticData;

    public function __construct($month, $year, $internationaldata, $domesticData)
    {
        $this->month = $month;
        $this->year = $year;
        $this->internationaldata = $internationaldata;
        $this->domesticData = $domesticData;
    }

    public function sheets(): array
    {
        $selectedMonth = $this->getMonth($this->month);
        $year = $this->year;
        return [
            new SynthStatSheet(
                'SYNTH',
                "SYNTHESE STATISTIQUES MENSUELLES IDEF $selectedMonth $year",
                $this->domesticData,
                $this->internationaldata
            ),
        ];
    }

    private function getMonth(string $month): string
    {
        $mois = $month;

        $voyelles = ['A', 'E', 'I', 'O', 'U', 'Y'];
        $prefixe = '';
        foreach ($voyelles as $voyelle) {
            if (str_starts_with($mois, $voyelle)) {
                $prefixe = "D'";
                break;
            }
        }
        if ($prefixe == '') {
            $prefixe = 'DE ';
        }

        return 'MOIS ' . $prefixe . $mois;
    }
}
