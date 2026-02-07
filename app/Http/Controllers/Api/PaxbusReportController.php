<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Collection;
use App\Exports\Paxbus\PaxbusWeeklyReportExport;
use App\Exports\Paxbus\PaxbusYearlyReportExport;
use App\Exports\Paxbus\PaxbusMonthlyReportExport;

class PaxbusReportController extends Controller
{
    /**
     * Génère le rapport mensuel par regime
     */
    public function monthlyReport(string|int $month, string|int $year, string $regime): array|\Illuminate\Http\JsonResponse
    {
        // On force la conversion en entier pour la logique interne (getDaysOfMonth, etc.)
        $month = (int) $month;
        $year = (int) $year;

        // Vérifier s'il y a des données de vols pour ce mois
        if (!$this->hasFlightData($month, $year, $regime)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $days = $this->getDaysOfMonth($month, $year);
        $operators = $this->getOperators($regime);

        // Utilisation de match (PHP 8+) pour plus de clarté
        $pax_data = match ($regime) {
            FlightRegimeEnum::INTERNATIONAL->value => $this->buildMonthlyInternationalSheetData($days, $operators),
            FlightRegimeEnum::DOMESTIC->value => $this->buildMonthlyDomesticSheetData($days, $operators),
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
    private function buildMonthlyInternationalSheetData(array $days, Collection $operators)
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
                    ->filter(fn($f) => Carbon::parse($f->departure_time)->format('Y-m-d') === $currentDay)
                    ->sum(fn($f) => $f->statistic['pax_bus'] ?? 0);

                $row[$op->sigle] = $total_pax;
            }

            return $row;
        })->toArray();
    }

    /**
     * Construit les données pour une feuille domestique
     */
    private function buildMonthlyDomesticSheetData(array $days, Collection $operators)
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
    public function monthlyExportReport(string $month = '11', string $year = '2025')
    {
        // On force le format int pour éviter les erreurs de type
        $monthInt = (int) $month;
        $yearInt = (int) $year;

        $internationalData = $this->monthlyReport($monthInt, $yearInt, FlightRegimeEnum::INTERNATIONAL->value);
        
        // Vérifier si une erreur a été retournée
        if ($internationalData instanceof \Illuminate\Http\JsonResponse) {
            return $internationalData;
        }
        
        $domesticData = $this->monthlyReport($monthInt, $yearInt, FlightRegimeEnum::DOMESTIC->value);
        
        // Vérifier si une erreur a été retournée
        if ($domesticData instanceof \Illuminate\Http\JsonResponse) {
            return $domesticData;
        }

        $monthName = $this->getMonthName($monthInt);
        $fileName = sprintf(
            'RAPPORT_MENSUEL_PAX_BUS_%s_%s.xlsx',
            $monthName,
            $yearInt
        );

        // On passe les tableaux complets (contenant 'pax' et 'operators')
        return Excel::download(
            new PaxbusMonthlyReportExport(
                $monthName,
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );
    }

    /**
     * Génère le rapport annuel par regime
     */
    public function yearlyReport(string|int $year, string $regime): array|\Illuminate\Http\JsonResponse
    {
        // On force la conversion en entier pour la logique interne (getDaysOfMonth, etc.)
        $year = (int) $year;

        // Vérifier s'il y a des données de vols pour cette année
        if (!$this->hasFlightData(null, $year, $regime)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $months = range(1, 12);
        $operators = $this->getOperators($regime);

        // Utilisation de match (PHP 8+) pour plus de clarté
        $pax_data = match ($regime) {
            FlightRegimeEnum::INTERNATIONAL->value => $this->buildYearlyInternationalSheetData($months, $year, $operators),
            FlightRegimeEnum::DOMESTIC->value => $this->buildYearlyDomesticSheetData($months, $year, $operators),
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
    private function buildYearlyInternationalSheetData(array $months, int $year, Collection $operators)
    {
        $rows = [];
        foreach ($months as $month) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = Carbon::create($year, $month, 1)->endOfMonth();

            $row = [];
            $allFlights = Flight::with('statistic')
                ->whereBetween('departure_time', [$start, $end])
                ->where('flight_regime', FlightRegimeEnum::INTERNATIONAL->value)
                ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
                ->where('flight_type', FlightTypeEnum::REGULAR->value)
                ->where('status', FlightStatusEnum::DEPARTED->value)
                ->get();

            $formattedMonth = Carbon::create($year, $month, 1)->format('m-Y');
            $currentMonth = Carbon::create($year, $month, 1)->format('Y-m');
            $row = ['date' => $formattedMonth];

            foreach ($operators as $operator) {
                $total_pax = $allFlights->where('operator_id', $operator->id)
                    ->filter(fn($f) => Carbon::parse($f->departure_time)->format('Y-m') === $currentMonth)
                    ->sum(fn($f) => $f->statistic['pax_bus'] ?? 0);

                $row[$operator->sigle] = $total_pax;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Construit les données pour une feuille domestique
     */
    private function buildYearlyDomesticSheetData(array $months, int $year, Collection $operators)
    {
        $rows = [];

        foreach ($months as $month) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = Carbon::create($year, $month, 1)->endOfMonth();
            $row = [];
            $allFlights = Flight::whereBetween('departure_time', [$start, $end])
                ->where('flight_regime', FlightRegimeEnum::DOMESTIC->value)
                ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
                ->where('flight_type', FlightTypeEnum::REGULAR->value)
                ->where('status', FlightStatusEnum::DEPARTED->value)
                ->get();

            $row = ['date' => Carbon::create($year, $month, 1)->format('m-Y')];

            foreach ($operators as $op) {
                $opAircrafts = $op->aircrafts;

                if ($opAircrafts->isEmpty()) {
                    continue;
                }

                $row[$op->sigle] = [];

                foreach ($opAircrafts as $aircraft) {
                    // 3. Filtrage en mémoire au lieu d'une requête SQL
                    $count = $allFlights->where('operator_id', $op->id)
                        ->where('aircraft_id', $aircraft->id)
                        ->count();
                    $row[$op->sigle][$aircraft->immatriculation] = [
                        'count' => $count,
                        'pmad' => $aircraft->pmad,
                    ];
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Exporte le rapport annuel en Excel (International + National)
     */
    public function yearlyExportReport(string $year = '2025')
    {
        // On force le format int pour éviter les erreurs de type
        $yearInt = (int) $year;

        $internationalData = $this->yearlyReport($yearInt, FlightRegimeEnum::INTERNATIONAL->value);
        
        // Vérifier si une erreur a été retournée
        if ($internationalData instanceof \Illuminate\Http\JsonResponse) {
            return $internationalData;
        }
        
        $domesticData = $this->yearlyReport($yearInt, FlightRegimeEnum::DOMESTIC->value);
        
        // Vérifier si une erreur a été retournée
        if ($domesticData instanceof \Illuminate\Http\JsonResponse) {
            return $domesticData;
        }

        $fileName = sprintf(
            'RAPPORT_ANNUEL_PAX_BUS_%s.xlsx',
            $yearInt
        );

        // On passe les tableaux complets (contenant 'pax' et 'operators')
        return Excel::download(
            new PaxbusYearlyReportExport(
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );
    }

    /**
     * Génère le rapport hebdomadaire (quinzaine)
     */
    public function weeklyReport(string $quinzaine, int $month, int $year, string $regime): array
    {
        // Déterminer la plage de dates selon la quinzaine
        [$startDate, $endDate] = $this->getQuinzaineDates($quinzaine, $month, $year);

        $days = $this->getDaysInRange($startDate, $endDate);
        $operators = $this->getOperators($regime);

        $data = match ($regime) {
            FlightRegimeEnum::INTERNATIONAL->value => $this->buildWeeklyInternationalData($days, $operators),
            FlightRegimeEnum::DOMESTIC->value => $this->buildWeeklyDomesticData($days, $operators),
            default => [],
        };

        return [
            'data' => $data,
            'operators' => $operators->pluck('sigle')->toArray(),
            'startDate' => $startDate->format('d/m/Y'),
            'endDate' => $endDate->format('d/m/Y'),
        ];
    }

    /**
     * Construit les données hebdomadaires pour l'international
     * Format : date => [sigleOperator => [immatriculation, pax_bus], ...]
     */
    private function buildWeeklyInternationalData(array $days, Collection $operators): array
    {
        $startDate = Carbon::parse($days[0])->startOfDay();
        $endDate = Carbon::parse(end($days))->endOfDay();

        // Récupérer tous les vols de la période
        $allFlights = Flight::with(['statistic', 'aircraft'])
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->where('flight_regime', FlightRegimeEnum::INTERNATIONAL->value)
            ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
            ->where('flight_type', FlightTypeEnum::REGULAR->value)
            ->where('status', FlightStatusEnum::DEPARTED->value)
            ->get()
            ->groupBy(function ($flight) {
                return Carbon::parse($flight->departure_time)->format('Y-m-d');
            });

        $result = [];

        foreach ($days as $day) {
            $dayFormatted = Carbon::parse($day)->format('d/m/Y');
            $flightsOfDay = $allFlights->get($day, collect());

            if ($flightsOfDay->isEmpty()) {
                continue; // Skip les jours sans vols
            }

            $result[$dayFormatted] = [];

            foreach ($operators as $operator) {
                $operatorFlights = $flightsOfDay->where('operator_id', $operator->id);

                foreach ($operatorFlights as $flight) {
                    $immatriculation = $flight->aircraft->immatriculation ?? 'N/A';
                    $paxBus = $flight->statistic['pax_bus'] ?? 0;

                    if (! isset($result[$dayFormatted][$operator->sigle])) {
                        $result[$dayFormatted][$operator->sigle] = [];
                    }

                    $result[$dayFormatted][$operator->sigle][] = [
                        'immatriculation' => $immatriculation,
                        'pax_bus' => $paxBus,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Construit les données hebdomadaires pour le domestique
     * Format : date => [sigleOperator => [immatriculation, pmad], ...]
     */
    private function buildWeeklyDomesticData(array $days, Collection $operators): array
    {
        $startDate = Carbon::parse($days[0])->startOfDay();
        $endDate = Carbon::parse(end($days))->endOfDay();

        // Récupérer tous les vols de la période
        $allFlights = Flight::with('aircraft')
            ->whereBetween('departure_time', [$startDate, $endDate])
            ->where('flight_regime', FlightRegimeEnum::DOMESTIC->value)
            ->where('flight_nature', FlightNatureEnum::COMMERCIAL->value)
            ->where('flight_type', FlightTypeEnum::REGULAR->value)
            ->where('status', FlightStatusEnum::DEPARTED->value)
            ->get()
            ->groupBy(function ($flight) {
                return Carbon::parse($flight->departure_time)->format('Y-m-d');
            });

        $result = [];

        foreach ($days as $day) {
            $dayFormatted = Carbon::parse($day)->format('d/m/Y');
            $flightsOfDay = $allFlights->get($day, collect());

            if ($flightsOfDay->isEmpty()) {
                continue;
            }

            $result[$dayFormatted] = [];

            foreach ($operators as $operator) {
                $operatorFlights = $flightsOfDay->where('operator_id', $operator->id);

                foreach ($operatorFlights as $flight) {
                    $aircraft = $flight->aircraft;
                    if (! $aircraft) {
                        continue;
                    }

                    $immatriculation = $aircraft->immatriculation;
                    $pmad = $aircraft->pmad;
                    $category = $pmad >= 50000 ? '≥50T' : '<50T';

                    if (! isset($result[$dayFormatted][$operator->sigle])) {
                        $result[$dayFormatted][$operator->sigle] = [];
                    }

                    $result[$dayFormatted][$operator->sigle][] = [
                        'immatriculation' => $immatriculation,
                        'pmad' => $pmad,
                        'category' => $category,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Exporte le rapport hebdomadaire
     */
    public function weeklyExportReport(string $quinzaine, string $month, string $year)
    {
        $monthInt = (int) $month;
        $yearInt = (int) $year;

        $internationalData = $this->weeklyReport($quinzaine, $monthInt, $yearInt, FlightRegimeEnum::INTERNATIONAL->value);
        $domesticData = $this->weeklyReport($quinzaine, $monthInt, $yearInt, FlightRegimeEnum::DOMESTIC->value);

        $monthName = $this->getMonthName($monthInt);
        $fileName = sprintf(
            'RAPPORT_HEBDOMADAIRE_PAX_BUS_%s_%s_%s.xlsx',
            strtoupper($quinzaine),
            $monthName,
            $yearInt
        );

        return Excel::download(
            new PaxbusWeeklyReportExport(
                $quinzaine,
                $monthName,
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );
    }

    /**
     * Détermine les dates de début et fin selon la quinzaine
     */
    private function getQuinzaineDates(string $quinzaine, int $month, int $year): array
    {
        $quinzaine = strtolower($quinzaine);

        if ($quinzaine === 'q1') {
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 15)->endOfDay();
        } else {
            $startDate = Carbon::create($year, $month, 16)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * Récupère les jours dans une plage de dates
     */
    private function getDaysInRange(Carbon $startDate, Carbon $endDate): array
    {
        $days = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $days[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $days;
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
            ->map(fn($day) => Carbon::create($year, $month, $day)->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Vérifie s'il y a des données de vols pour une année ou un mois spécifique
     */
    private function hasFlightData(?int $month, int $year, string $regime): bool
    {
        $query = Flight::where('flight_regime', $regime);

        if ($month !== null) {
            // Vérification pour un mois spécifique
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            $query->whereBetween('departure_time', [$startDate, $endDate]);
        } else {
            // Vérification pour une année complète
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
            $query->whereBetween('departure_time', [$startDate, $endDate]);
        }

        return $query->exists();
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
