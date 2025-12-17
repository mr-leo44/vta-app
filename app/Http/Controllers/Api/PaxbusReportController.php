<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Exports\Paxbus\PaxbusReportExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Collection;

class PaxbusReportController extends Controller
{
    /**
     * Génère le rapport mensuel international
     */
    public function monthlyInternationalReport(int $month, int $year): array
    {
        $days = $this->getDaysOfMonth($month, $year);
        $operators = $this->getOperators(FlightRegimeEnum::INTERNATIONAL->value);

        return [
            'pax' => $this->buildInternationalSheetData($days, $operators),
            'operators' => $operators->pluck('sigle')->toArray(),
        ];
    }

    /**
     * Construit les données pour une feuille
     */
    private function buildInternationalSheetData(
        array $days,
        Collection $operators,
    ) {
        return collect($days)->map(function ($day) use ($operators) {
            $row = ['date' => Carbon::parse($day)->format('d/m/Y')];

            foreach ($operators as $op) {
                $flights = Flight::with('statistic')
                    ->where('flight_regime', FlightRegimeEnum::INTERNATIONAL)
                    ->where('operator_id', $op->id)
                    ->whereDate('departure_time', $day)
                    ->where('flight_nature', FlightNatureEnum::COMMERCIAL)
                    ->where('flight_type', FlightTypeEnum::REGULAR)
                    ->where('status', FlightStatusEnum::DEPARTED)->get();

                $total_pax = 0;

                foreach ($flights as $flight) {
                    $total_pax += $flight->statistic['pax_bus'];
                }
                $row[$op->sigle] = $total_pax;
            }

            return $row;
        });
    }

    /**
     * Exporte le rapport mensuel en Excel
     */
    public function exportMonthlyReport(
        string $month = '11',
        string $year = '2025',
    ) {
        $reportData = $this->monthlyInternationalReport((int) $month, (int) $year);
        $monthName = $this->getMonthName((int) $month);

        $fileName = sprintf(
            'RAPPORT MENSUEL PAX BUS %s %s.xlsx',
            $monthName,
            $year
        );

        return Excel::download(
            new PaxbusReportExport($month, $year, $reportData),
            $fileName
        );
    }


    /**
     * Récupère les opérateurs ayant des vols pour un régime donné
     * pas par une colonne dans la table operators
     */
    private function getOperators(string $regime): Collection
    {
        $query = Operator::whereHas('flights', function ($q) use ($regime) {
            $q->where('flight_regime', $regime)
                ->where('flight_nature', FlightNatureEnum::COMMERCIAL)
                ->where('status', FlightStatusEnum::DEPARTED);
        });
        if ($regime === 'domestic') {
            $query->whereHas('flights', function ($q) use ($regime) {
                $q->where('flight_regime', $regime)
                    ->where('flight_nature', FlightNatureEnum::COMMERCIAL)
                    ->where('status', FlightStatusEnum::DEPARTED)
                    ->whereHas('statistic', function ($sq) {
                        $sq->where(function ($s) {
                            $s->where('passengers_count', '>', 0)
                                ->orWhere('pax_bus', '>', 0)
                                ->orWhere('go_pass_count', '>', 0);
                        });
                    });
            });
        }

        return $query->orderBy('sigle')->get();
    }


    /**
     * Récupère les jours d'un mois
     */
    private function getDaysOfMonth(int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return collect(range(1, $daysInMonth))
            ->map(fn($day) => Carbon::create($year, $month, $day)->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Récupère le nom du mois
     */
    private function getMonthName(int $month): string
    {
        $monthNames = [
            1 => 'JANVIER',
            2 => 'FÉVRIER',
            3 => 'MARS',
            4 => 'AVRIL',
            5 => 'MAI',
            6 => 'JUIN',
            7 => 'JUILLET',
            8 => 'AOÛT',
            9 => 'SEPTEMBRE',
            10 => 'OCTOBRE',
            11 => 'NOVEMBRE',
            12 => 'DÉCEMBRE'
        ];

        return $monthNames[$month] ?? 'INCONNU';
    }
}
