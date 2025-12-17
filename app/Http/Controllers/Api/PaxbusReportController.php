<?php

namespace App\Http\Controllers\Api;

use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Enums\FlightTypeEnum;
use App\Exports\Paxbus\PaxbusReportExport;
use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Operator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class PaxbusReportController extends Controller
{
    /**
     * Génère le rapport mensuel international
     */
    public function monthlyReport(string|int $month, string|int $year, string $regime): array
    {
        // On force la conversion en entier pour la logique interne (getDaysOfMonth, etc.)
        $month = (int) $month;
        $year = (int) $year;

        $days = $this->getDaysOfMonth($month, $year);
        $operators = $this->getOperators($regime);

        // Utilisation de match (PHP 8+) pour plus de clarté
        $pax_data = match ($regime) {
            FlightRegimeEnum::INTERNATIONAL->value => $this->buildInternationalSheetData($days, $operators),
            FlightRegimeEnum::DOMESTIC->value => $this->buildDomesticSheetData($days, $operators),
            default => [],
        };

        return [
            'pax' => $pax_data,
            'operators' => $operators->pluck('sigle')->toArray(),
        ];
    }

    /**
     * Construit les données pour une feuille internationale
     */
    private function buildInternationalSheetData(array $days, Collection $operators)
    {
        $startDate = Carbon::parse($days[0])->startOfMonth();
        $endDate = Carbon::parse($days[0])->endOfMonth();

        $allFlights = Flight::with('statistic')
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->where('flight_regime', FlightRegimeEnum::INTERNATIONAL->value)
            ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
            ->where('flight_type', FlightTypeEnum::REGULAR->value)
            ->where('status', FlightStatusEnum::DEPARTED->value)
            ->get();

        return collect($days)->map(function ($day) use ($operators, $allFlights) {
            $row = ['date' => Carbon::parse($day)->format('d/m/Y')];
            $currentDay = Carbon::parse($day)->format('Y-m-d');

            foreach ($operators as $op) {
                // 2. Filtrer la collection en mémoire (pas de SQL ici)
                $total_pax = $allFlights->where('operator_id', $op->id)
                    ->filter(fn ($f) => Carbon::parse($f->departure_time)->format('Y-m-d') === $currentDay)
                    ->sum(fn ($f) => $f->statistic['pax_bus'] ?? 0);

                $row[$op->sigle] = $total_pax;
            }

            return $row;
        })->toArray();
    }

    /**
     * Construit les données pour une feuille domestique
     */
    private function buildDomesticSheetData(array $days, Collection $operators)
    {
        // 1. Récupération de la plage de dates
        $startDate = Carbon::parse(reset($days))->startOfDay();
        $endDate = Carbon::parse(end($days))->endOfDay();

        // 2. Récupérer TOUS les vols domestiques du mois en une seule requête
        // On charge aussi la relation 'aircraft' pour éviter les problèmes de propriétés manquantes
        $allFlights = Flight::whereBetween('departure_time', [$startDate, $endDate])
            ->where('flight_regime', FlightRegimeEnum::DOMESTIC->value)
            ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
            ->where('flight_type', FlightTypeEnum::REGULAR->value)
            ->where('status', FlightStatusEnum::DEPARTED->value)
            ->get()
            ->groupBy(function ($flight) {
                // On groupe par date pour faciliter la recherche interne
                return Carbon::parse($flight->departure_time)->format('Y-m-d');
            });

        return collect($days)->map(function ($day) use ($operators, $allFlights) {
            $row = ['date' => Carbon::parse($day)->format('d/m/Y')];

            // Récupérer les vols du jour spécifique depuis notre collection groupée
            $flightsOfDay = $allFlights->get($day, collect());

            foreach ($operators as $op) {
                $opAircrafts = $op->aircrafts;

                if ($opAircrafts->isEmpty()) {
                    continue;
                }

                $row[$op->sigle] = [];

                foreach ($opAircrafts as $aircraft) {
                    // 3. Filtrage en mémoire au lieu d'une requête SQL
                    $count = $flightsOfDay->where('operator_id', $op->id)
                        ->where('aircraft_id', $aircraft->id)
                        ->count();

                    $row[$op->sigle][$aircraft->immatriculation] = [
                        'count' => $count,
                        'pmad' => $aircraft->pmad,
                    ];
                }
            }

            return $row;
        })->toArray();
    }

    /**
     * Exporte le rapport mensuel en Excel (International + National)
     */
    public function monthlyExportReport(string $month = "11", string $year = "2025")
    {
        // On force le format int pour éviter les erreurs de type
        $monthInt = (int) $month;
        $yearInt = (int) $year;

        $internationalData = $this->monthlyReport($monthInt, $yearInt, FlightRegimeEnum::INTERNATIONAL->value);
        $domesticData = $this->monthlyReport($monthInt, $yearInt, FlightRegimeEnum::DOMESTIC->value);

        $monthName = $this->getMonthName($monthInt);
        $fileName = sprintf(
            'RAPPORT_MENSUEL_PAX_BUS_%s_%s.xlsx',
            $monthName,
            $yearInt
        );

        // On passe les tableaux complets (contenant 'pax' et 'operators')
        return Excel::download(
            new PaxbusReportExport(
                $monthName,
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );

    }

    /**
     * Récupère les opérateurs ayant des vols pour un régime donné
     */
    private function getOperators(string $regime): Collection
    {
        $query = Operator::with('aircrafts')->whereHas('flights', function ($q) use ($regime) {
            $q->where('flight_regime', $regime)
                ->where('flight_nature', FlightNatureEnum::COMMERCIAL)
                ->where('status', FlightStatusEnum::DEPARTED);
        });

        if ($regime === FlightRegimeEnum::DOMESTIC->value) {
            $query->whereHas('flights', function ($q) use ($regime) {
                $q->where('flight_regime', $regime)
                    ->where('flight_nature', FlightNatureEnum::COMMERCIAL)
                    ->where('status', FlightStatusEnum::DEPARTED)
                    ->whereHas('statistic', function ($sq) {
                        $sq->where(function ($s) {
                            $s->where('passengers_count', '>', 0)
                                ->orWhere('pax_bus', '>', 0);
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
            ->map(fn ($day) => Carbon::create($year, $month, $day)->format('Y-m-d'))
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
            12 => 'DÉCEMBRE',
        ];

        return $monthNames[$month] ?? 'INCONNU';
    }
}
