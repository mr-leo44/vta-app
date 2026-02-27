<?php

namespace App\Http\Controllers\Api;

use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Enums\FlightTypeEnum;
use App\Exports\Idef\IdefAnnualReportExport;
use App\Exports\Idef\IdefReportExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Operator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class IdefReportController extends Controller
{
    /**
     * Génère le rapport mensuel par regime avec datasets (un par métrique)
     */
    public function monthlyReport(string|int $month, string|int $year, string $regime): array|JsonResponse
    {
        $month = (int) $month;
        $year = (int) $year;

        // Vérifier s'il y a des données de vols pour ce mois
        if (!$this->hasFlightData($month, $year, $regime)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $days = $this->getDaysOfMonth($month, $year);
        $paxOperators = $this->getOperators($regime, true);
        $allOperators = $this->getOperators($regime);

        if ($regime == FlightRegimeEnum::INTERNATIONAL->value) {
            return [
                'pax' => $this->buildSheetData($days, $regime, 'pax', $allOperators),
                'fret' => $this->buildSheetData($days, $regime, 'fret', $allOperators),
                'exced' => $this->buildSheetData($days, $regime, 'exced', $allOperators),
                'operators' => [
                    'pax' => $paxOperators->pluck('sigle')->toArray(),
                    'fret' => $allOperators->pluck('sigle')->toArray(),
                ]
            ];
        } else {
            return [
                'pax' => $this->buildSheetData($days, $regime, 'pax', $allOperators),
                'fret' => $this->buildSheetData($days, $regime, 'fret', $allOperators),
                'exced' => $this->buildSheetData($days, $regime, 'exced', $allOperators),
                'operators' => [
                    'pax' => $paxOperators->pluck('sigle')->toArray(),
                    'fret' => $allOperators->pluck('sigle')->toArray(),
                ]
            ];
        }
    }

    /**
     * Génère le rapport annuel par regime avec datasets (un par métrique)
     */
    public function yearlyReport(string|int $year, string $regime): array|JsonResponse
    {
        $year = (int) $year;

        // Vérifier s'il y a des données de vols pour ce mois
        if (!$this->hasFlightData(null, $year, $regime)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $months = range(1, 12);

        $paxOperators = $this->getOperators($regime, true);
        $allOperators = $this->getOperators($regime);
        if ($regime == FlightRegimeEnum::INTERNATIONAL->value) {
            return [
                'pax' => $this->buildAnnualSheetData($months, $year, $regime, 'pax', $allOperators),
                'fret' => $this->buildAnnualSheetData($months, $year, $regime, 'fret', $allOperators),
                'exced' => $this->buildAnnualSheetData($months, $year, $regime, 'exced', $allOperators),
                'operators' => [
                    'pax' => $paxOperators->pluck('sigle')->toArray(),
                    'fret' => $allOperators->pluck('sigle')->toArray(),
                ]
            ];
        } else {
            return [
                'pax' => $this->buildAnnualSheetData($months, $year, $regime, 'pax', $allOperators),
                'fret' => $this->buildAnnualSheetData($months, $year, $regime, 'fret', $allOperators),
                'exced' => $this->buildAnnualSheetData($months, $year, $regime, 'exced', $allOperators),
                'operators' => [
                    'pax' => $paxOperators->pluck('sigle')->toArray(),
                    'fret' => $allOperators->pluck('sigle')->toArray(),
                ]
            ];
        }
    }

    /**
     * Exporte le rapport mensuel en Excel
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
            'IDEF_%s_%s.xlsx',
            $monthName,
            $year
        );

        return Excel::download(
            new IdefReportExport(
                $monthName,
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );
    }

    /**
     * Exporte le rapport annuel en Excel
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
            'RAPPORT_ANNUEL_IDEF_%s.xlsx',
            $year
        );

        return Excel::download(
            new IdefAnnualReportExport(
                $yearInt,
                $internationalData,
                $domesticData
            ),
            $fileName
        );
    }


    /**
     * Construit les données pour une feuille (une métrique spécifique)
     */
    private function buildSheetData(
        array $days,
        string $regime,
        string $metric,
        Collection $allOps
    ): array {
        return collect($days)->map(function ($day) use ($regime, $metric, $allOps) {
            $row = ['DATE' => Carbon::parse($day)->format('d/m/Y')];

            // All operators
            foreach ($allOps as $op) {
                $row[$op->sigle] = $this->getMetricValue(
                    $day,
                    $regime,
                    $op->id,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES = Commerciaux non-réguliers
            $row['VNR'] = $this->getMetricValue(
                $day,
                $regime,
                null,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            return $row;
        })->toArray();
    }

    /**
     * Construit les données pour une feuille (une métrique spécifique)
     */
    private function buildAnnualSheetData(
        array $months,
        int $year,
        string $regime,
        string $metric,
        Collection $allOps
    ): array {
        return collect($months)->map(function ($month) use ($regime, $year, $metric, $allOps) {
            $row = ['MOIS' => Carbon::create($year, $month, 1)->format('m-Y')];

            // Commercial operators
            foreach ($allOps as $op) {
                $row[$op->sigle] = $this->getAnnualMetricValue(
                    $month,
                    $year,
                    $regime,
                    $op->id,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES = Commerciaux non-réguliers
            $row['VNR'] = $this->getAnnualMetricValue(
                $month,
                $year,
                $regime,
                null,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            return $row;
        })->toArray();
    }

    /**
     * Récupère la valeur d'une métrique spécifique
     */
    private function getMetricValue(
        string $day,
        string $regime,
        ?int $operatorId,
        FlightTypeEnum $type,
        string $metric
    ): int|float|array {
        $flights = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereDate('departure_time', $day)
            ->where('flight_type', $type->value)
            ->where('status', FlightStatusEnum::DEPARTED);

        if ($operatorId !== null) {
            $flights->where('operator_id', $operatorId);
        }

        $stats = $flights->get();

        if ($regime === FlightRegimeEnum::INTERNATIONAL->value) {
            $totals = [
                'pax' => [
                    'trafic' => 0,
                    'gopass' => 0,
                    'justifications' => []
                ],
                'fret' => [
                    'departure' => 0,
                    'arrival' => 0
                ],
                'exced' => [
                    'departure' => 0,
                    'arrival' => 0
                ],
            ];
        } else {
            $totals = [
                'pax' => [
                    'trafic' => 0,
                    'gopass' => 0,
                    'justifications' => []
                ],
                'fret' =>  0,
                'exced' => 0
            ];
        }

        foreach ($stats as $flight) {
            $stat = $flight->statistic;
            if (!$stat) continue;

            // Totaux standards
            if ($regime === FlightRegimeEnum::INTERNATIONAL->value) {
                $totals['pax']['trafic'] += (int)($stat->passengers_count ?? 0);
                $totals['pax']['gopass'] += (int)($stat->go_pass_count ?? 0);
                $totals['fret']['departure']   += (int)($stat->fret_count['departure'] ?? 0);
                $totals['fret']['arrival']  += (int)($stat->fret_count['arrival'] ?? 0);
                $totals['exced']['departure']  += (int)($stat->excedents['departure'] ?? 0);
                $totals['exced']['arrival'] += (int)($stat->excedents['arrival'] ?? 0);
            } else {
                $totals['pax']['trafic'] += (int)($stat->passengers_count ?? 0);
                $totals['pax']['gopass'] += (int)($stat->go_pass_count ?? 0);
                $totals['fret'] += (int)($stat->fret_count['departure'] ?? 0);
                $totals['exced'] += (int)($stat->excedents['departure'] ?? 0);
            }

            // Traitement des justifications
            if ($stat->has_justification && is_array($stat->justification)) {
                foreach ($stat->justification as $key => $value) {

                    if (is_array($value)) {
                        // Cas "Militaires" ou autres tableaux imbriqués
                        if (!isset($totals['pax']['justifications'][$key])) {
                            $totals['pax']['justifications'][$key] = [];
                        }

                        foreach ($value as $subKey => $subValue) {
                            if (!isset($totals['pax']['justifications'][$key][$subKey])) {
                                $totals['pax']['justifications'][$key][$subKey] = 0;
                            }
                            $totals['pax']['justifications'][$key][$subKey] += (int)$subValue;
                        }
                    } else {
                        // Cas "Inad", "Staff", etc. (valeurs simples)
                        if (!isset($totals['pax']['justifications'][$key])) {
                            $totals['pax']['justifications'][$key] = 0;
                        }
                        $totals['pax']['justifications'][$key] += (int)$value;
                    }
                }
            }
        }

        return $totals[$metric] ?? $totals;
    }

    /**
     * Récupère la valeur d'une métrique annuelle spécifique
     */
    private function getAnnualMetricValue(
        string $month,
        int $year,
        string $regime,
        ?int $operatorId,
        FlightTypeEnum $type,
        string $metric
    ): int|float|array {

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $flights = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
            ->where('flight_type', $type->value)
            ->where('status', FlightStatusEnum::DEPARTED);

        if ($operatorId !== null) {
            $flights->where('operator_id', $operatorId);
        }

        $stats = $flights->get();

        if ($regime === FlightRegimeEnum::INTERNATIONAL->value) {
            $totals = [
                'pax' => [
                    'trafic' => 0,
                    'gopass' => 0,
                    'justifications' => []
                ],
                'fret' => [
                    'departure' => 0,
                    'arrival' => 0
                ],
                'exced' => [
                    'departure' => 0,
                    'arrival' => 0
                ],
            ];
        } else {
            $totals = [
                'pax' => [
                    'trafic' => 0,
                    'gopass' => 0,
                    'justifications' => []
                ],
                'fret' =>  0,
                'exced' => 0
            ];
        }

        foreach ($stats as $flight) {
            $stat = $flight->statistic;
            if (!$stat) continue;

            // Totaux standards
            if ($regime === FlightRegimeEnum::INTERNATIONAL->value) {
                $totals['pax']['trafic'] += (int)($stat->passengers_count ?? 0);
                $totals['pax']['gopass'] += (int)($stat->go_pass_count ?? 0);
                $totals['fret']['departure']   += (int)($stat->fret_count['departure'] ?? 0);
                $totals['fret']['arrival']  += (int)($stat->fret_count['arrival'] ?? 0);
                $totals['exced']['departure']  += (int)($stat->excedents['departure'] ?? 0);
                $totals['exced']['arrival'] += (int)($stat->excedents['arrival'] ?? 0);
            } else {
                $totals['pax']['trafic'] += (int)($stat->passengers_count ?? 0);
                $totals['pax']['gopass'] += (int)($stat->go_pass_count ?? 0);
                $totals['fret'] += (int)($stat->fret_count['departure'] ?? 0);
                $totals['exced'] += (int)($stat->excedents['departure'] ?? 0);
            }

            // Traitement des justifications
            if ($stat->has_justification && is_array($stat->justification)) {
                foreach ($stat->justification as $key => $value) {

                    if (is_array($value)) {
                        // Cas "Militaires" ou autres tableaux imbriqués
                        if (!isset($totals['pax']['justifications'][$key])) {
                            $totals['pax']['justifications'][$key] = [];
                        }

                        foreach ($value as $subKey => $subValue) {
                            if (!isset($totals['pax']['justifications'][$key][$subKey])) {
                                $totals['pax']['justifications'][$key][$subKey] = 0;
                            }
                            $totals['pax']['justifications'][$key][$subKey] += (int)$subValue;
                        }
                    } else {
                        // Cas "Inad", "Staff", etc. (valeurs simples)
                        if (!isset($totals['pax']['justifications'][$key])) {
                            $totals['pax']['justifications'][$key] = 0;
                        }
                        $totals['pax']['justifications'][$key] += (int)$value;
                    }
                }
            }
        }

        return $totals[$metric] ?? $totals;
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
     * Récupère les opérateurs ayant des vols pour un régime donné
     * La nature du vol est déterminée par la relation avec les flights,
     * pas par une colonne dans la table operators
     */
    private function getOperators(string $regime, bool $excludeCargoOnly = false): Collection
    {
        $query = Operator::whereHas('flights', function ($q) use ($regime) {
            $q->where('flight_regime', $regime)
                ->where('status', FlightStatusEnum::DEPARTED);
        });

        // Pour le sheet PAX : exclure les opérateurs qui n'ont QUE des vols cargo
        if ($excludeCargoOnly) {
            $query->whereHas('flights', function ($q) use ($regime) {
                $q->where('flight_regime', $regime)
                    ->where('status', FlightStatusEnum::DEPARTED)
                    ->whereHas('statistic', function ($sq) {
                        $sq->where('passengers_count', '>', 0);
                    });
            });
        }

        return $query->orderBy('sigle')->get();
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
