<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Exports\VTAFreightSynthAnnualExport;
use App\Exports\VTAFreightSynthExport;
use App\Exports\VTATrafficAnnualReportExport;
use App\Exports\VTATrafficReportExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group ReportManagement
 *
 * Unified IDEF statistics report.
 *
 * Combines PAX (traffic / gopass / paxbus), freight and excedents
 * for both domestic and international regimes in a single response.
 *
 * Route prefix : /api/report
 */
class ReportController extends Controller
{
    // =========================================================================
    // PUBLIC — AGGREGATED
    // =========================================================================

    /**
     * Monthly report — one row per calendar day, all operators merged.
     *
     * GET /report/monthly/{month}/{year}
     *
     * Response shape:
     * {
     *   "domestic": {
     *     "pax":       [{ "DATE":"01/02/2026", "traffic":120, "gopass":95, "paxbus":88 }, …],
     *     "fret":      [{ "DATE":"01/02/2026", "traffic":450, "idef":380 }, …],
     *     "excedents": [{ "DATE":"01/02/2026", "traffic":12,  "idef":10  }, …]
     *   },
     *   "international": {
     *     "pax":           [{ "DATE":…, "traffic":…, "gopass":…, "paxbus":… }, …],
     *     "fret_depart":   [{ "DATE":…, "traffic":…, "idef":…  }, …],
     *     "fret_arrivee":  […],
     *     "exced_depart":  […],
     *     "exced_arrivee": […]
     *   }
     * }
     */
    public function monthly(string|int $month, string|int $year): array|JsonResponse
    {
        [$month, $year] = [(int) $month, (int) $year];

        if (!$this->hasData($month, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $days = $this->getDaysOfMonth($month, $year);

        return [
            'domestic'      => $this->buildMonthlyRegime($days, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildMonthlyRegime($days, FlightRegimeEnum::INTERNATIONAL->value),
        ];
    }

    /**
     * Annual report — one row per month, all operators merged.
     *
     * GET /report/yearly/{year}
     *
     * Same shape as monthly but keyed "MOIS" → "MM-YYYY".
     */
    public function yearly(string|int $year): array|JsonResponse
    {
        $year = (int) $year;

        if (!$this->hasData(null, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $months = range(1, 12);

        return [
            'domestic'      => $this->buildAnnualRegime($months, $year, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildAnnualRegime($months, $year, FlightRegimeEnum::INTERNATIONAL->value),
        ];
    }

    // =========================================================================
    // PUBLIC — BY OPERATOR (one total per operator, no time breakdown)
    // =========================================================================

    /**
     * Monthly report — one cumulative total per operator for the whole month.
     *
     * GET /report/monthly/{month}/{year}/by-operators
     *
     * Response shape:
     * {
     *   "domestic": {
     *     "pax":      { "AA": { "traffic":500, "gopass":420, "paxbus":410 }, "BB": {…} },
     *     "fret":     { "AA": { "traffic":1200, "idef":980 }, … },
     *     "excedents":{ "AA": { "traffic":30, "idef":25 }, … }
     *   },
     *   "international": {
     *     "pax":           { "AA": { "traffic":…, "gopass":…, "paxbus":… }, … },
     *     "fret_depart":   { "AA": { "traffic":…, "idef":… }, … },
     *     "fret_arrivee":  {…},
     *     "exced_depart":  {…},
     *     "exced_arrivee": {…}
     *   }
     * }
     */
    public function monthlyByOperators(string|int $month, string|int $year): array|JsonResponse
    {
        [$month, $year] = [(int) $month, (int) $year];

        if (!$this->hasData($month, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        return [
            'domestic'      => $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::INTERNATIONAL->value),
        ];
    }

    /**
     * Annual report — one cumulative total per operator for the whole year.
     *
     * GET /report/yearly/{year}/by-operators
     */
    public function yearlyByOperators(string|int $year): array|JsonResponse
    {
        $year = (int) $year;

        if (!$this->hasData(null, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end   = Carbon::create($year, 12, 31)->endOfYear();

        return [
            'domestic'      => $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::INTERNATIONAL->value),
        ];
    }

    // =========================================================================
    // PUBLIC — EXPORTS
    // =========================================================================

    /**
     * Export monthly traffic report to Excel (2 sheets: domestic + international).
     *
     * GET /report/monthly/{month}/{year}/export
     */
    public function monthlyExport(string|int $month, string|int $year)
    {
        [$monthInt, $yearInt] = [(int) $month, (int) $year];

        $domesticData = $this->monthly($monthInt, $yearInt);
        if ($domesticData instanceof JsonResponse) return $domesticData;

        // Re-fetch as separate arrays for the export classes
        if (!$this->hasData($monthInt, $yearInt)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $days     = $this->getDaysOfMonth($monthInt, $yearInt);
        $domestic      = $this->buildMonthlyRegime($days, FlightRegimeEnum::DOMESTIC->value);
        $international = $this->buildMonthlyRegime($days, FlightRegimeEnum::INTERNATIONAL->value);
        $monthName     = $this->getMonthName($monthInt);

        return Excel::download(
            new VTATrafficReportExport($monthName, $yearInt, $domestic, $international),
            sprintf('EVOLUTION_TRAFIC_%s_%s.xlsx', $monthName, $yearInt)
        );
    }

    /**
     * Export annual traffic report to Excel (2 sheets: domestic + international).
     *
     * GET /report/yearly/{year}/export
     */
    public function yearlyExport(string|int $year)
    {
        $yearInt = (int) $year;

        if (!$this->hasData(null, $yearInt)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $months        = range(1, 12);
        $domestic      = $this->buildAnnualRegime($months, $yearInt, FlightRegimeEnum::DOMESTIC->value);
        $international = $this->buildAnnualRegime($months, $yearInt, FlightRegimeEnum::INTERNATIONAL->value);

        return Excel::download(
            new VTATrafficAnnualReportExport($yearInt, $domestic, $international),
            sprintf('EVOLUTION_TRAFIC_%s.xlsx', $yearInt)
        );
    }

    /**
     * Export monthly freight synthesis by operators to Excel (4 sheets).
     *
     * GET /report/monthly/{month}/{year}/by-operators/export
     */
    public function monthlyByOperatorsExport(string|int $month, string|int $year)
    {
        [$monthInt, $yearInt] = [(int) $month, (int) $year];

        if (!$this->hasData($monthInt, $yearInt)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $start = Carbon::create($yearInt, $monthInt, 1)->startOfMonth();
        $end   = Carbon::create($yearInt, $monthInt, 1)->endOfMonth();

        $domestic      = $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::DOMESTIC->value);
        $international = $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::INTERNATIONAL->value);
        $monthName     = $this->getMonthName($monthInt);

        return Excel::download(
            new VTAFreightSynthExport($monthName, $yearInt, $domestic, $international),
            sprintf('TABLEAU SYNTHESE DE FRET_%s_%s.xlsx', $monthName, $yearInt)
        );
    }

    /**
     * Export annual freight synthesis by operators to Excel (4 sheets).
     *
     * GET /report/yearly/{year}/by-operators/export
     */
    public function yearlyByOperatorsExport(string|int $year)
    {
        $yearInt = (int) $year;

        if (!$this->hasData(null, $yearInt)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $start = Carbon::create($yearInt, 1, 1)->startOfYear();
        $end   = Carbon::create($yearInt, 12, 31)->endOfYear();

        $domestic      = $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::DOMESTIC->value);
        $international = $this->buildTotalsByOperator($start, $end, FlightRegimeEnum::INTERNATIONAL->value);

        return Excel::download(
            new VTAFreightSynthAnnualExport($yearInt, $domestic, $international),
            sprintf('TABLEAU SYNTHESE DE FRET_%s.xlsx', $yearInt)
        );
    }
    // =========================================================================

    /**
     * One row per day, all operators summed together.
     * Single DB query for the whole month, filtering done in memory.
     */
    private function buildMonthlyRegime(array $days, string $regime): array
    {
        $start   = Carbon::parse($days[0])->startOfDay();
        $end     = Carbon::parse(end($days))->endOfDay();
        $flights = $this->fetchFlights($start, $end, $regime);
        $isInt   = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($days as $day) {
            $dayFlights = $flights->filter(
                fn($f) => Carbon::parse($f->departure_time)->format('Y-m-d') === $day
            );

            $s     = $this->aggregate($dayFlights, $regime);
            $label = ['DATE' => Carbon::parse($day)->format('d/m/Y')];

            $pax[]      = $label + ['traffic' => $s['pax'],       'gopass' => $s['gopass'], 'paxbus' => $s['paxbus']];
            $fretDep[]  = $label + ['traffic' => $s['fret_dep'],  'idef'   => $s['idef_fret_dep']];
            $excedDep[] = $label + ['traffic' => $s['exced_dep'], 'idef'   => $s['idef_exced_dep']];

            if ($isInt) {
                $fretArr[]  = $label + ['traffic' => $s['fret_arr'],  'idef' => $s['idef_fret_arr']];
                $excedArr[] = $label + ['traffic' => $s['exced_arr'], 'idef' => $s['idef_exced_arr']];
            }
        }

        return $this->shapeRegime($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    /**
     * One row per month, all operators summed together.
     * Single DB query for the whole year, filtering done in memory.
     */
    private function buildAnnualRegime(array $months, int $year, string $regime): array
    {
        $start   = Carbon::create($year, 1, 1)->startOfYear();
        $end     = Carbon::create($year, 12, 31)->endOfYear();
        $flights = $this->fetchFlights($start, $end, $regime);
        $isInt   = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($months as $month) {
            $monthFlights = $flights->filter(function ($f) use ($month, $year) {
                $d = Carbon::parse($f->departure_time);
                return $d->month === $month && $d->year === $year;
            });

            $s     = $this->aggregate($monthFlights, $regime);
            $label = ['MOIS' => Carbon::create($year, $month, 1)->format('m-Y')];

            $pax[]      = $label + ['traffic' => $s['pax'],       'gopass' => $s['gopass'], 'paxbus' => $s['paxbus']];
            $fretDep[]  = $label + ['traffic' => $s['fret_dep'],  'idef'   => $s['idef_fret_dep']];
            $excedDep[] = $label + ['traffic' => $s['exced_dep'], 'idef'   => $s['idef_exced_dep']];

            if ($isInt) {
                $fretArr[]  = $label + ['traffic' => $s['fret_arr'],  'idef' => $s['idef_fret_arr']];
                $excedArr[] = $label + ['traffic' => $s['exced_arr'], 'idef' => $s['idef_exced_arr']];
            }
        }

        return $this->shapeRegime($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // BUILDER — BY OPERATOR (totals only, split commercial / non_commercial)
    // =========================================================================

    /**
     * One keyed entry per operator, grouped by commercial / non_commercial.
     * Single DB query per nature group for the period, filtered in memory.
     *
     * Response shape for each regime:
     * {
     *   "commercial": {
     *     "pax":      { "AA": { traffic, gopass, paxbus }, … },
     *     "fret":     { "AA": { traffic, idef }, … },
     *     "excedents":{ "AA": { traffic, idef }, … }
     *   },
     *   "non_commercial": { … same structure … }
     * }
     */
    private function buildTotalsByOperator(Carbon $start, Carbon $end, string $regime): array
    {
        return [
            'commercial'     => $this->buildOperatorGroup($start, $end, $regime, true),
            'non_commercial' => $this->buildOperatorGroup($start, $end, $regime, false),
        ];
    }

    /**
     * Aggregate totals for one nature group (commercial or non-commercial).
     */
    private function buildOperatorGroup(
        Carbon $start,
        Carbon $end,
        string $regime,
        bool   $isCommercial
    ): array {
        $flights   = $this->fetchFlightsByNature($start, $end, $regime, $isCommercial);
        $operators = $this->getOperators($regime, $isCommercial);
        $isInt     = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($operators as $op) {
            $opFlights = $flights->where('operator_id', $op->id);
            $s         = $this->aggregate($opFlights, $regime);

            $pax[$op->sigle]      = ['traffic' => $s['pax'],       'gopass' => $s['gopass'], 'paxbus' => $s['paxbus']];
            $fretDep[$op->sigle]  = ['traffic' => $s['fret_dep'],  'idef'   => $s['idef_fret_dep']];
            $excedDep[$op->sigle] = ['traffic' => $s['exced_dep'], 'idef'   => $s['idef_exced_dep']];

            if ($isInt) {
                $fretArr[$op->sigle]  = ['traffic' => $s['fret_arr'],  'idef' => $s['idef_fret_arr']];
                $excedArr[$op->sigle] = ['traffic' => $s['exced_arr'], 'idef' => $s['idef_exced_arr']];
            }
        }

        return $this->shapeRegime($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // AGGREGATION
    // =========================================================================

    /**
     * Fetch flights for a period and regime — single query, eager-loads
     * statistic and operator relations.
     */
    private function fetchFlights(Carbon $start, Carbon $end, string $regime): Collection
    {
        return Flight::with(['statistic', 'operator'])
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
            ->where('status', FlightStatusEnum::DEPARTED)
            ->get();
    }

    /**
     * Same as fetchFlights but scoped to a flight nature group.
     * Used by buildOperatorGroup to keep commercial and non-commercial
     * flights separate without mixing them in memory.
     */
    private function fetchFlightsByNature(
        Carbon $start,
        Carbon $end,
        string $regime,
        bool   $isCommercial
    ): Collection {
        $natures = $isCommercial
            ? [FlightNatureEnum::COMMERCIAL->value]
            : collect(FlightNatureEnum::nonCommercial())->pluck('value')->toArray();

        return Flight::with(['statistic', 'operator'])
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
            ->whereIn('flight_nature', $natures)
            ->where('status', FlightStatusEnum::DEPARTED)
            ->get();
    }

    /**
     * Sum all metrics from a flight collection.
     *
     * UN operator flights count toward traffic but NOT toward idef columns
     * (they are exonerated and thus excluded from IDEF billing).
     */
    private function aggregate(Collection $flights, string $regime): array
    {
        $s = [
            'pax'            => 0, 'gopass'         => 0, 'paxbus'         => 0,
            'fret_dep'       => 0, 'fret_arr'       => 0,
            'idef_fret_dep'  => 0, 'idef_fret_arr'  => 0,
            'exced_dep'      => 0, 'exced_arr'      => 0,
            'idef_exced_dep' => 0, 'idef_exced_arr' => 0,
        ];

        foreach ($flights as $flight) {
            $stat = $flight->statistic;
            if (!$stat) continue;

            $isUN = ($flight->operator?->sigle === 'UN');

            $s['pax']    += (int) ($stat->passengers_count ?? 0);
            $s['gopass'] += (int) ($stat->go_pass_count    ?? 0);
            $s['paxbus'] += (int) ($stat->pax_bus          ?? 0);

            $fd = (int) ($stat->fret_count['departure'] ?? 0);
            $fa = (int) ($stat->fret_count['arrival']   ?? 0);
            $s['fret_dep'] += $fd;
            $s['fret_arr'] += $fa;
            if (!$isUN) {
                $s['idef_fret_dep'] += $fd;
                $s['idef_fret_arr'] += $fa;
            }

            $ed = (int) ($stat->excedents['departure'] ?? 0);
            $ea = (int) ($stat->excedents['arrival']   ?? 0);
            $s['exced_dep'] += $ed;
            $s['exced_arr'] += $ea;
            if (!$isUN) {
                $s['idef_exced_dep'] += $ed;
                $s['idef_exced_arr'] += $ea;
            }
        }

        return $s;
    }

    /**
     * Normalise metric key names for domestic vs international.
     *
     * Domestic     → fret / excedents             (departure only)
     * International → fret_depart / fret_arrivee / exced_depart / exced_arrivee
     */
    private function shapeRegime(
        bool  $isInt,
        array $pax,
        array $fretDep,
        array $excedDep,
        array $fretArr  = [],
        array $excedArr = []
    ): array {
        if ($isInt) {
            return [
                'pax'            => $pax,
                'fret_depart'    => $fretDep,
                'fret_arrivee'   => $fretArr,
                'exced_depart'   => $excedDep,
                'exced_arrivee'  => $excedArr,
            ];
        }

        return [
            'pax'       => $pax,
            'fret'      => $fretDep,
            'excedents' => $excedDep,
        ];
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Return true if at least one departed flight exists in the period
     * (checked across both regimes so a partial month is not rejected).
     */
    private function hasData(?int $month, int $year): bool
    {
        $q = Flight::where('status', FlightStatusEnum::DEPARTED);

        if ($month !== null) {
            $q->whereBetween('departure_time', [
                Carbon::createFromDate($year, $month, 1)->startOfMonth(),
                Carbon::createFromDate($year, $month, 1)->endOfMonth(),
            ]);
        } else {
            $q->whereBetween('departure_time', [
                Carbon::createFromDate($year, 1, 1)->startOfYear(),
                Carbon::createFromDate($year, 12, 31)->endOfYear(),
            ]);
        }

        return $q->exists();
    }

    /**
     * Operators with at least one departed flight of the given nature
     * for the given regime.
     *
     * @param bool $isCommercial  true → COMMERCIAL flights only
     *                            false → all non-commercial natures
     */
    private function getOperators(string $regime, bool $isCommercial = true): Collection
    {
        $natures = $isCommercial
            ? [FlightNatureEnum::COMMERCIAL->value]
            : collect(FlightNatureEnum::nonCommercial())->pluck('value')->toArray();

        return Operator::whereHas('flights', fn($q) => $q
            ->where('flight_regime', $regime)
            ->whereIn('flight_nature', $natures)
            ->where('status', FlightStatusEnum::DEPARTED)
        )
        ->orderBy('sigle')
        ->get();
    }

    /**
     * Every day of the month as an array of YYYY-MM-DD strings.
     */
    private function getDaysOfMonth(int $month, int $year): array
    {
        return collect(range(1, Carbon::create($year, $month, 1)->daysInMonth))
            ->map(fn($d) => Carbon::create($year, $month, $d)->format('Y-m-d'))
            ->toArray();
    }

    /**
     * French month name.
     */
    private function getMonthName(int $month): string
    {
        return [
            1  => 'JANVIER',  2  => 'FÉVRIER',   3  => 'MARS',
            4  => 'AVRIL',    5  => 'MAI',        6  => 'JUIN',
            7  => 'JUILLET',  8  => 'AOÛT',       9  => 'SEPTEMBRE',
            10 => 'OCTOBRE',  11 => 'NOVEMBRE',   12 => 'DÉCEMBRE',
        ][$month] ?? 'INCONNU';
    }
}