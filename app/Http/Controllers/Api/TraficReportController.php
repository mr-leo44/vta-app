<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use Illuminate\Http\Request;
use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightStatusEnum;
use App\Http\Controllers\Controller;
use App\Exports\TraficReportExport;
use Maatwebsite\Excel\Facades\Excel;

class TraficReportController extends Controller
{
    /**
     * Génère le rapport mensuel avec 5 datasets (un par métrique)
     */
    public function monthlyReport($month = '11', $year = '2025', $regime = 'international')
    {
        $days = $this->getDaysOfMonth($month, $year);

        // Pour PAX : exclure les opérateurs cargo-only
        $commercialOpsPax = $this->getOperators(FlightNatureEnum::COMMERCIAL, $regime, true);
        $nonCommercialOpsPax = $this->getOperators(FlightNatureEnum::NON_COMMERCIAL, $regime, true);

        // Pour fret et excédents : tous les opérateurs
        $commercialOps = $this->getOperators(FlightNatureEnum::COMMERCIAL, $regime, false);
        $nonCommercialOps = $this->getOperators(FlightNatureEnum::NON_COMMERCIAL, $regime, false);

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
     * Construit les données pour une feuille (une métrique spécifique)
     */
    private function buildSheetData($days, $regime, $metric, $commercialOps, $nonCommercialOps)
    {
        $rows = [];

        foreach ($days as $day) {
            $row = [
                'date' => Carbon::parse($day)->format('d/m/Y'),
            ];

            // === COMMERCIAL OPERATORS ===
            foreach ($commercialOps as $op) {
                $row[$op->sigle] = $this->getMetricValue(
                    $day,
                    $regime,
                    $op->id,
                    FlightNatureEnum::COMMERCIAL,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES = Commerciaux non-réguliers
            $row['AUTRES'] = $this->getMetricValue(
                $day,
                $regime,
                null,
                FlightNatureEnum::COMMERCIAL,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            // === NON-COMMERCIAL OPERATORS ===
            foreach ($nonCommercialOps as $op) {
                $row[$op->sigle] = $this->getMetricValue(
                    $day,
                    $regime,
                    $op->id,
                    FlightNatureEnum::NON_COMMERCIAL,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // AUTRES_NC = Non-Commerciaux non-réguliers
            $row['AUTRES_NC'] = $this->getMetricValue(
                $day,
                $regime,
                null,
                FlightNatureEnum::NON_COMMERCIAL,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Récupère les opérateurs ayant des vols pour un régime donné
     */
    private function getOperators($nature, $regime, $excludeCargoOnly = false)
    {
        $query = Operator::where('flight_nature', $nature)
            ->whereHas(
                'flights',
                fn($q) => $q
                    ->where('flight_regime', $regime)
                    ->where('status', FlightStatusEnum::LANDED)
            );

        // Pour le sheet PAX : exclure les opérateurs qui n'ont QUE des vols cargo
        if ($excludeCargoOnly) {
            $query->whereHas('flights', function ($q) use ($regime) {
                $q->where('flight_regime', $regime)
                    ->where('status', FlightStatusEnum::LANDED)
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
    private function getMetricValue($day, $regime, $operatorId, $nature, $type, $metric)
    {
        // Passer le paramètre $metric à sumFlightStats
        $stats = $this->sumFlightStats($day, $regime, $operatorId, $nature, $type, $metric);
        return $stats[$metric] ?? 0;
    }

    /**
     * Somme les statistiques des vols pour un jour donné
     */
    private function sumFlightStats($day, $regime, $operatorId, $natureEnum, $typeEnum, $metric = null)
    {
        $query = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereDate('departure_time', $day)
            ->where('flight_nature', $natureEnum->value)
            ->where('flight_type', $typeEnum->value)
            ->where('status', FlightStatusEnum::LANDED);

        if ($operatorId !== null) {
            $query->where('operator_id', $operatorId);
        }

        $flights = $query->get();

        $totals = [
            'pax' => 0,
            'fret_depart' => 0,
            'fret_arrivee' => 0,
            'exced_depart' => 0,
            'exced_arrivee' => 0,
        ];

        foreach ($flights as $f) {
            $stat = $f->statistic;
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
    public function exportMonthlyReport($month = '11', $year = '2025', $regime = 'international')
    {
        $reportData = $this->monthlyReport($month, $year, $regime);
        $monthName = $this->getMonth($month);
        $formatted_regime = $regime === "domestic" ? 'NATIONAL' : 'INTERNATIONAL';

        $fileName = sprintf(
            'TRAFIC_%s_%s_%s.xlsx',
            strtoupper($formatted_regime),
            strtoupper($monthName),
            strtoupper($year)
        );

        return Excel::download(
            new TraficReportExport($regime, $month, $year, $reportData),
            $fileName
        );
    }

    /**
     * Récupère les jours d'un mois
     */
    private function getDaysOfMonth($month, $year)
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * Récupère le nom du mois
     */
    private function getMonth($month): string
    {
        $monthNames = [
            '01' => 'JANVIER',
            '02' => 'FÉVRIER',
            '03' => 'MARS',
            '04' => 'AVRIL',
            '05' => 'MAI',
            '06' => 'JUIN',
            '07' => 'JUILLET',
            '08' => 'AOÛT',
            '09' => 'SEPTEMBRE',
            '10' => 'OCTOBRE',
            '11' => 'NOVEMBRE',
            '12' => 'DÉCEMBRE'
        ];

        $monthNumber = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        return $monthNames[$monthNumber] ?? 'INCONNU';
    }
}
