<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use Illuminate\Http\Request;
use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Exports\TraficReportExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TraficReportAnnualExport;
use Illuminate\Support\Collection;

class TraficReportController extends Controller
{
    private const METRICS = ['pax', 'fret_depart', 'fret_arrivee', 'exced_depart', 'exced_arrivee'];

    /**
     * Génère le rapport mensuel avec 5 datasets (un par métrique)
     */
    public function monthlyReport(int $month, int $year, string $regime): array
    {
        $days = $this->getDaysOfMonth($month, $year);

        // Pour PAX : exclure les opérateurs cargo-only
        $commercialOpsPax = $this->getOperators(true, $regime, true);
        $nonCommercialOpsPax = $this->getOperators(false, $regime, true);

        // Pour fret et excédents : tous les opérateurs
        $commercialOps = $this->getOperators(true, $regime, false);
        $nonCommercialOps = $this->getOperators(false, $regime, false);

        return [
            'pax' => $this->buildSheetData($days, $regime, 'pax', $commercialOpsPax, $nonCommercialOpsPax),
            'fret_depart' => $this->buildSheetData($days, $regime, 'fret_depart', $commercialOps, $nonCommercialOps),
            'fret_arrivee' => $this->buildSheetData($days, $regime, 'fret_arrivee', $commercialOps, $nonCommercialOps),
            'exced_depart' => $this->buildSheetData($days, $regime, 'exced_depart', $commercialOps, $nonCommercialOps),
            'exced_arrivee' => $this->buildSheetData($days, $regime, 'exced_arrivee', $commercialOps, $nonCommercialOps),
            'operators' => [
                'pax' => [
                    'commercial' => $commercialOpsPax->pluck('sigle')->toArray(),
                    'non_commercial' => $nonCommercialOpsPax->pluck('sigle')->toArray(),
                ],
                'fret' => [
                    'commercial' => $commercialOps->pluck('sigle')->toArray(),
                    'non_commercial' => $nonCommercialOps->pluck('sigle')->toArray(),
                ]
            ]
        ];
    }

    /**
     * Génère le rapport annuel avec 5 datasets (un par métrique)
     */
    public function yearlyReport(int $year, string $regime): array
    {
        $months = range(1, 12);

        // Pour PAX : exclure les opérateurs cargo-only
        $commercialOpsPax = $this->getOperators(true, $regime, true);
        $nonCommercialOpsPax = $this->getOperators(false, $regime, true);

        // Pour fret et excédents : tous les opérateurs
        $commercialOps = $this->getOperators(true, $regime, false);
        $nonCommercialOps = $this->getOperators(false, $regime, false);

        return [
            'pax' => $this->buildAnnualSheetData($months, $year, $regime, 'pax', $commercialOpsPax, $nonCommercialOpsPax),
            'fret_depart' => $this->buildAnnualSheetData($months, $year, $regime, 'fret_depart', $commercialOps, $nonCommercialOps),
            'fret_arrivee' => $this->buildAnnualSheetData($months, $year, $regime, 'fret_arrivee', $commercialOps, $nonCommercialOps),
            'exced_depart' => $this->buildAnnualSheetData($months, $year, $regime, 'exced_depart', $commercialOps, $nonCommercialOps),
            'exced_arrivee' => $this->buildAnnualSheetData($months, $year, $regime, 'exced_arrivee', $commercialOps, $nonCommercialOps),
            'operators' => [
                'pax' => [
                    'commercial' => $commercialOpsPax->pluck('sigle')->toArray(),
                    'non_commercial' => $nonCommercialOpsPax->pluck('sigle')->toArray(),
                ],
                'fret' => [
                    'commercial' => $commercialOps->pluck('sigle')->toArray(),
                    'non_commercial' => $nonCommercialOps->pluck('sigle')->toArray(),
                ]
            ]
        ];
    }

    /**
     * Construit les données pour une feuille (une métrique spécifique)
     */
    private function buildSheetData(
        array $days,
        string $regime,
        string $metric,
        Collection $commercialOps,
        Collection $nonCommercialOps
    ): array {
        return collect($days)->map(function ($day) use ($regime, $metric, $commercialOps, $nonCommercialOps) {
            $row = ['date' => Carbon::parse($day)->format('d/m/Y')];

            // Commercial operators
            foreach ($commercialOps as $op) {
                $row[$op->sigle] = $this->getMetricValue(
                    $day,
                    $regime,
                    $op->id,
                    true,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES = Commerciaux non-réguliers
            $row['AUTRES'] = $this->getMetricValue(
                $day,
                $regime,
                null,
                true,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            // Non-commercial operators
            foreach ($nonCommercialOps as $op) {
                $row[$op->sigle] = $this->getMetricValue(
                    $day,
                    $regime,
                    $op->id,
                    false,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES_NC = Non-Commerciaux non-réguliers
            $row['AUTRES_NC'] = $this->getMetricValue(
                $day,
                $regime,
                null,
                false,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            return $row;
        })->toArray();
    }

    /**
     * Construit les données pour un rapport annuel
     */
    private function buildAnnualSheetData(
        array $months,
        int $year,
        string $regime,
        string $metric,
        Collection $commercialOps,
        Collection $nonCommercialOps
    ): array {
        return collect($months)->map(function ($month) use ($year, $regime, $metric, $commercialOps, $nonCommercialOps) {
            $row = ['date' => Carbon::create($year, $month, 1)->format('m-Y')];

            // Commercial operators
            foreach ($commercialOps as $op) {
                $row[$op->sigle] = $this->getAnnualMetricValue(
                    $month,
                    $year,
                    $regime,
                    $op->id,
                    true,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES = Commerciaux non-réguliers
            $row['AUTRES'] = $this->getAnnualMetricValue(
                $month,
                $year,
                $regime,
                null,
                true,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            // Non-commercial operators
            foreach ($nonCommercialOps as $op) {
                $row[$op->sigle] = $this->getAnnualMetricValue(
                    $month,
                    $year,
                    $regime,
                    $op->id,
                    false,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES_NC = Non-Commerciaux non-réguliers
            $row['AUTRES_NC'] = $this->getAnnualMetricValue(
                $month,
                $year,
                $regime,
                null,
                false,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            return $row;
        })->toArray();
    }

    /**
     * Récupère les opérateurs ayant des vols pour un régime donné
     * La nature du vol est déterminée par la relation avec les flights, 
     * pas par une colonne dans la table operators
     */
    private function getOperators(bool $isCommercial, string $regime, bool $excludeCargoOnly = false): Collection
    {
        $natures = $isCommercial
            ? [FlightNatureEnum::COMMERCIAL->value]
            : collect(FlightNatureEnum::nonCommercial())->pluck('value')->toArray();

        $query = Operator::whereHas('flights', function ($q) use ($regime, $natures) {
            $q->where('flight_regime', $regime)
                ->whereIn('flight_nature', $natures)
                ->where('status', FlightStatusEnum::DEPARTED);
        });

        // Pour le sheet PAX : exclure les opérateurs qui n'ont QUE des vols cargo
        if ($excludeCargoOnly) {
            $query->whereHas('flights', function ($q) use ($regime, $natures) {
                $q->where('flight_regime', $regime)
                    ->whereIn('flight_nature', $natures)
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
     * Récupère la valeur d'une métrique spécifique
     */
    private function getMetricValue(
        string $day,
        string $regime,
        ?int $operatorId,
        bool $isCommercial,
        FlightTypeEnum $type,
        string $metric
    ): int|float {
        $stats = $this->sumFlightStats($day, $regime, $operatorId, $isCommercial, $type);
        return $stats[$metric] ?? 0;
    }

    /**
     * Récupère la valeur d'une métrique pour un mois
     */
    private function getAnnualMetricValue(
        int $month,
        int $year,
        string $regime,
        ?int $operatorId,
        bool $isCommercial,
        FlightTypeEnum $type,
        string $metric
    ): int|float {
        $stats = $this->sumMonthlyFlightStats($month, $year, $regime, $operatorId, $isCommercial, $type);
        return $stats[$metric] ?? 0;
    }

    /**
     * Somme les statistiques des vols pour un jour donné
     */
    private function sumFlightStats(
        string $day,
        string $regime,
        ?int $operatorId,
        bool $isCommercial,
        FlightTypeEnum $typeEnum
    ): array {
        $natures = $isCommercial
            ? [FlightNatureEnum::COMMERCIAL->value]
            : collect(FlightNatureEnum::nonCommercial())->pluck('value')->toArray();

        $query = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereDate('departure_time', $day)
            ->whereIn('flight_nature', $natures)
            ->where('flight_type', $typeEnum->value)
            ->where('status', FlightStatusEnum::DEPARTED);

        if ($operatorId !== null) {
            $query->where('operator_id', $operatorId);
        }

        return $this->aggregateFlightStatistics($query->get());
    }

    /**
     * Somme les statistiques des vols pour un mois
     */
    private function sumMonthlyFlightStats(
        int $month,
        int $year,
        string $regime,
        ?int $operatorId,
        bool $isCommercial,
        FlightTypeEnum $typeEnum
    ): array {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $natures = $isCommercial
            ? [FlightNatureEnum::COMMERCIAL->value]
            : collect(FlightNatureEnum::nonCommercial())->pluck('value')->toArray();

        $query = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
            ->whereIn('flight_nature', $natures)
            ->where('flight_type', $typeEnum->value)
            ->where('status', FlightStatusEnum::DEPARTED);

        if ($operatorId !== null) {
            $query->where('operator_id', $operatorId);
        }

        return $this->aggregateFlightStatistics($query->get());
    }

    /**
     * Agrège les statistiques d'une collection de vols
     */
    private function aggregateFlightStatistics(Collection $flights): array
    {
        $totals = [
            'pax' => 0,
            'fret_depart' => 0,
            'fret_arrivee' => 0,
            'exced_depart' => 0,
            'exced_arrivee' => 0,
        ];

        foreach ($flights as $flight) {
            $stat = $flight->statistic;
            if (!$stat) continue;

            $totals['pax'] += $stat->passengers_count ?? 0;
            $totals['fret_depart'] += $stat->fret_count['departure'] ?? 0;
            $totals['fret_arrivee'] += $stat->fret_count['arrival'] ?? 0;
            $totals['exced_depart'] += $stat->excedents['departure'] ?? 0;
            $totals['exced_arrivee'] += $stat->excedents['arrival'] ?? 0;
        }

        return $totals;
    }

    /**
     * Exporte le rapport mensuel en Excel
     */
    public function exportMonthlyReport(
        string $month = '11',
        string $year = '2025',
        string $regime = 'international'
    ) {
        $reportData = $this->monthlyReport((int) $month, (int) $year, $regime);
        $monthName = $this->getMonthName((int) $month);
        $formattedRegime = $regime === 'domestic' ? 'NATIONAL' : 'INTERNATIONAL';

        $fileName = sprintf(
            'TRAFIC_%s_%s_%s.xlsx',
            $formattedRegime,
            $monthName,
            $year
        );

        return Excel::download(
            new TraficReportExport($regime, $month, $year, $reportData),
            $fileName
        );
    }

    /**
     * Exporte le rapport annuel en Excel
     */
    public function exportYearlyReport(string $year, string $regime)
    {
        $reportData = $this->yearlyReport((int) $year, $regime);
        $formattedRegime = $regime === 'domestic' ? 'NATIONAL' : 'INTERNATIONAL';

        $fileName = sprintf(
            'TRAFIC_ANNUEL_%s_%s.xlsx',
            $formattedRegime,
            $year
        );

        return Excel::download(
            new TraficReportAnnualExport($regime, $year, $reportData),
            $fileName
        );
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