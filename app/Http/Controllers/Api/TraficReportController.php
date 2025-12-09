<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use Illuminate\Http\Request;
use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightStatusEnum;
use App\Exports\TraficReportExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TraficReportAnnualExport;

class TraficReportController extends Controller
{
    /**
     * Génère le rapport mensuel avec 5 datasets (un par métrique)
     */
    public function monthlyReport($month, $year, $regime)
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
     * Génère le rapport annuel avec 5 datasets (un par métrique)
     *
     * @param int $year L'année du rapport
     * @param string $regime Le régime du rapport
     *
     * @return array Les données du rapport annuel
     */
    public function yearlyReport($year, $regime)
    {
        // Les mois de l'année
        $months = range(1, 12);

        // Les opérateurs commerciaux et non-commerciaux
        // Pour PAX : exclure les opérateurs cargo-only
        $commercialOpsPax = $this->getOperators(FlightNatureEnum::COMMERCIAL, $regime, true);
        $nonCommercialOpsPax = $this->getOperators(FlightNatureEnum::NON_COMMERCIAL, $regime, true);

        // Pour fret et excédents : tous les opérateurs
        $commercialOps = $this->getOperators(FlightNatureEnum::COMMERCIAL, $regime, false);
        $nonCommercialOps = $this->getOperators(FlightNatureEnum::NON_COMMERCIAL, $regime, false);

        return [
            // Les statistiques pour les passagers
            'pax' => $this->buildAnualSheetData($months, $year, $regime, 'pax', $commercialOpsPax, $nonCommercialOpsPax),
            // Les statistiques pour les frets (départ)
            'fret_depart' => $this->buildAnualSheetData($months, $year, $regime, 'fret_depart', $commercialOps, $nonCommercialOps),
            // Les statistiques pour les frets (arrivée)
            'fret_arrivee' => $this->buildAnualSheetData($months, $year, $regime, 'fret_arrivee', $commercialOps, $nonCommercialOps),
            // Les statistiques pour les excédents (départ)
            'exced_depart' => $this->buildAnualSheetData($months, $year, $regime, 'exced_depart', $commercialOps, $nonCommercialOps),
            // Les statistiques pour les excédents (arrivée)
            'exced_arrivee' => $this->buildAnualSheetData($months, $year, $regime, 'exced_arrivee', $commercialOps, $nonCommercialOps),
            // Les opérateurs impliqués
            'operators' => [
                // Les opérateurs commerciaux et non-commerciaux impliqués
                // pour les passagers
                'pax' => [
                    'commercial' => $commercialOpsPax->pluck('sigle')->toArray(),
                    'non_commercial' => $nonCommercialOpsPax->pluck('sigle')->toArray(),
                ],
                // Les opérateurs commerciaux et non-commerciaux impliqués
                // pour les frets et les excédents
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
     * Construit les données pour un rapport annuel
     *
     * @param array $months Les mois de l'année
     * @param int $year L'année du rapport
     * @param string $regime Le régime du rapport
     * @param string $metric La métrique à récupérer
     * @param \Illuminate\Support\Collection $commercialOps Les opérateurs commerciaux
     * @param \Illuminate\Support\Collection $nonCommercialOps Les opérateurs non-commerciaux
     *
     * @return array Les données pour le rapport annuel
     */
    private function buildAnualSheetData($months, $year, $regime, $metric, $commercialOps, $nonCommercialOps)
    {
        $rows = [];

        // Parcourir chaque mois de l'année
        foreach ($months as $month) {
            $row = [
                // CORRECT : construire une vraie date du mois/année
                'date' => Carbon::create($year, $month, 1)->format('m-Y'),
            ];

            // Parcourir chaque opérateur commercial
            foreach ($commercialOps as $op) {
                $row[$op->sigle] = $this->getAnualMetricValue(
                    $month,
                    $year,
                    $regime,
                    $op->id,
                    FlightNatureEnum::COMMERCIAL,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // Calculer la valeur pour les opérateurs commerciaux non-réguliers
            $row['AUTRES'] = $this->getAnualMetricValue(
                $month,
                $year,
                $regime,
                null,
                FlightNatureEnum::COMMERCIAL,
                FlightTypeEnum::NON_REGULAR,
                $metric
            );

            // Parcourir chaque opérateur non-commercial
            foreach ($nonCommercialOps as $op) {
                $row[$op->sigle] = $this->getAnualMetricValue(
                    $month,
                    $year,
                    $regime,
                    $op->id,
                    FlightNatureEnum::NON_COMMERCIAL,
                    FlightTypeEnum::REGULAR,
                    $metric
                );
            }

            // Calculer la valeur pour les opérateurs non-commerciaux non-réguliers
            $row['AUTRES_NC'] = $this->getAnualMetricValue(
                $month,
                $year,
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

    private function getAnualMetricValue($month, $year, $regime, $operatorId, $nature, $type, $metric)
    {
        // Passer le paramètre $metric à sumFlightStats
        $stats = $this->sumMonthlyFlightStats($month, $year, $regime, $operatorId, $nature, $type, $metric);
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

    private function sumMonthlyFlightStats($month, $year, $regime, $operatorId, $natureEnum, $typeEnum, $metric = null)
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
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
     *
     * @param string $month Le mois du rapport (par exemple '11' pour novembre)
     * @param string $year L'année du rapport (par exemple '2025')
     * @param string $regime Le régime du rapport (par exemple 'domestic' ou 'international')
     *
     * @return \Illuminate\Http\Response Le fichier Excel exporté
     */
    public function exportMonthlyReport($month = '11', $year = '2025', $regime = 'international')
    {
        // Récupère les données du rapport mensuel
        $reportData = $this->monthlyReport($month, $year, $regime);

        // Récupère le nom du mois
        $monthName = $this->getMonth($month);

        // Récupère le nom du régime en majuscules
        $formatted_regime = $regime === "domestic" ? 'NATIONAL' : 'INTERNATIONAL';

        // Récupère le nom du fichier Excel
        $fileName = sprintf(
            'TRAFIC_%s_%s_%s.xlsx',
            strtoupper($formatted_regime),
            strtoupper($monthName),
            strtoupper($year)
        );

        // Exporte le fichier Excel
        return Excel::download(
            new TraficReportExport($regime, $month, $year, $reportData),
            $fileName
        );
    }

    /**
     * Exporte le rapport annuel en Excel
     *
     * @param string $year L'année du rapport
     * @param string $regime Le régime du rapport (domestic ou international)
     *
     * @return \Illuminate\Http\Response Le fichier Excel exporté
     */
    public function exportYearlyReport($year, $regime)
    {
        // Récupère les données du rapport annuel
        $reportData = $this->yearlyReport($year, $regime);

        // Format le nom du régime
        $formatted_regime = $regime === "domestic" ? 'NATIONAL' : 'INTERNATIONAL';

        // Format le nom du fichier
        $fileName = sprintf(
            'TRAFIC_ANNUEL_%s_%s.xlsx',
            strtoupper($formatted_regime),
            strtoupper($year)
        );

        // Exporte le fichier Excel
        return Excel::download(
            new TraficReportAnnualExport($regime, $year, $reportData),
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
