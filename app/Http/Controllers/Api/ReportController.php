<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Flight;
use App\Models\Operator;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use App\Enums\FlightTypeEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\IdefFretServiceInterface;
use App\Services\MonthlyRateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * @group ReportManagement
 *
 * Unified IDEF statistics report controller.
 *
 * Combines PAX (traffic / gopass / paxbus), freight and excedents
 * for both domestic and international regimes in a single response,
 * either aggregated or broken down per operator.
 *
 * Route prefix : /api/report
 */
class ReportController extends Controller
{
    public function __construct(
        protected IdefFretServiceInterface   $idefFretService,
        protected MonthlyRateServiceInterface $monthlyRateService,
    ) {}

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Monthly report — aggregated by regime.
     *
     * GET /report/monthly/{month}/{year}
     *
     * Returns one row per calendar day for every metric, merged across both
     * regimes, plus the raw IDEF fret entries and the monthly exchange rate.
     *
     * Response shape:
     * {
     *   "domestic": {
     *     "pax":       [{ "DATE":"01/02/2026", "traffic":120, "gopass":95, "paxbus":88 }, …],
     *     "fret":      [{ "DATE":"01/02/2026", "traffic":450, "idef":380 }, …],
     *     "excedents": [{ "DATE":"01/02/2026", "traffic":12,  "idef":10  }, …]
     *   },
     *   "international": {
     *     "pax":          [{ "DATE":…, "traffic":…, "gopass":…, "paxbus":… }, …],
     *     "fret_depart":  [{ "DATE":…, "traffic":…, "idef":…  }, …],
     *     "fret_arrivee": […],
     *     "exced_depart": […],
     *     "exced_arrivee":[…]
     *   },
     *   "idef_fret":    [{ "DATE":"01/02/2026", "usd":120, "cdf":500 }, …],
     *   "monthly_rate": 2850
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
            'idef_fret'     => $this->idefFretByDays($month, $year),
            'monthly_rate'  => $this->monthlyRateService->findByMonth((string) $month, (string) $year)?->rate,
        ];
    }

    /**
     * Annual report — aggregated by regime.
     *
     * GET /report/yearly/{year}
     *
     * Same shape as monthly but one row per month (keyed "MOIS" → "MM-YYYY").
     * idef_fret contains monthly totals; each row also carries the exchange rate
     * for that specific month.
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
            'idef_fret'     => $this->idefFretByMonths($months, $year),
        ];
    }

    /**
     * Monthly report — broken down by operator within each regime.
     *
     * GET /report/monthly/{month}/{year}/by-operators
     *
     * Each metric dataset is keyed by operator sigle instead of being flat.
     *
     * Response shape:
     * {
     *   "domestic": {
     *     "pax": {
     *       "AA": [{ "DATE":"01/02/2026", "traffic":45, "gopass":38, "paxbus":36 }, …],
     *       "BB": […]
     *     },
     *     "fret":      { "AA": […], "BB": […] },
     *     "excedents": { "AA": […], "BB": […] }
     *   },
     *   "international": { … same structure … },
     *   "idef_fret":    […],
     *   "monthly_rate": 2850
     * }
     */
    public function monthlyByOperators(string|int $month, string|int $year): array|JsonResponse
    {
        [$month, $year] = [(int) $month, (int) $year];

        if (!$this->hasData($month, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $days = $this->getDaysOfMonth($month, $year);

        return [
            'domestic'      => $this->buildMonthlyByOperator($days, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildMonthlyByOperator($days, FlightRegimeEnum::INTERNATIONAL->value),
            'idef_fret'     => $this->idefFretByDays($month, $year),
            'monthly_rate'  => $this->monthlyRateService->findByMonth((string) $month, (string) $year)?->rate,
        ];
    }

    /**
     * Annual report — broken down by operator within each regime.
     *
     * GET /report/yearly/{year}/by-operators
     */
    public function yearlyByOperators(string|int $year): array|JsonResponse
    {
        $year = (int) $year;

        if (!$this->hasData(null, $year)) {
            return ApiResponse::error('Pas de données disponibles', 400);
        }

        $months = range(1, 12);

        return [
            'domestic'      => $this->buildAnnualByOperator($months, $year, FlightRegimeEnum::DOMESTIC->value),
            'international' => $this->buildAnnualByOperator($months, $year, FlightRegimeEnum::INTERNATIONAL->value),
            'idef_fret'     => $this->idefFretByMonths($months, $year),
        ];
    }

    // =========================================================================
    // MONTHLY BUILDERS — AGGREGATED
    // =========================================================================

    /**
     * Build every metric for one regime, one row per day.
     *
     * A single DB query fetches all flights for the period; filtering is then
     * done in memory to avoid N+1 query problems.
     */
    private function buildMonthlyRegime(array $days, string $regime): array
    {
        $start   = Carbon::parse($days[0])->startOfDay();
        $end     = Carbon::parse(end($days))->endOfDay();
        $flights = $this->fetchFlights($start, $end, $regime);

        $isInt = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax      = [];
        $fretDep  = [];
        $fretArr  = [];
        $excedDep = [];
        $excedArr = [];

        foreach ($days as $day) {
            $dayFlights = $flights->filter(
                fn($f) => Carbon::parse($f->departure_time)->format('Y-m-d') === $day
            );

            $stats = $this->aggregate($dayFlights, $regime);
            $label = ['DATE' => Carbon::parse($day)->format('d/m/Y')];

            $pax[]     = $label + $this->paxRow($stats);
            $fretDep[] = $label + ['traffic' => $stats['fret_dep'], 'idef' => $stats['idef_fret_dep']];
            $excedDep[] = $label + ['traffic' => $stats['exced_dep'], 'idef' => $stats['idef_exced_dep']];

            if ($isInt) {
                $fretArr[]  = $label + ['traffic' => $stats['fret_arr'],  'idef' => $stats['idef_fret_arr']];
                $excedArr[] = $label + ['traffic' => $stats['exced_arr'], 'idef' => $stats['idef_exced_arr']];
            }
        }

        return $this->shapeResult($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // ANNUAL BUILDERS — AGGREGATED
    // =========================================================================

    private function buildAnnualRegime(array $months, int $year, string $regime): array
    {
        $start   = Carbon::create($year, 1, 1)->startOfYear();
        $end     = Carbon::create($year, 12, 31)->endOfYear();
        $flights = $this->fetchFlights($start, $end, $regime);

        $isInt = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($months as $month) {
            $monthFlights = $flights->filter(function ($f) use ($month, $year) {
                $d = Carbon::parse($f->departure_time);
                return $d->month === $month && $d->year === $year;
            });

            $stats = $this->aggregate($monthFlights, $regime);
            $label = ['MOIS' => Carbon::create($year, $month, 1)->format('m-Y')];

            $pax[]      = $label + $this->paxRow($stats);
            $fretDep[]  = $label + ['traffic' => $stats['fret_dep'],  'idef' => $stats['idef_fret_dep']];
            $excedDep[] = $label + ['traffic' => $stats['exced_dep'], 'idef' => $stats['idef_exced_dep']];

            if ($isInt) {
                $fretArr[]  = $label + ['traffic' => $stats['fret_arr'],  'idef' => $stats['idef_fret_arr']];
                $excedArr[] = $label + ['traffic' => $stats['exced_arr'], 'idef' => $stats['idef_exced_arr']];
            }
        }

        return $this->shapeResult($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // MONTHLY BUILDERS — BY OPERATOR
    // =========================================================================

    private function buildMonthlyByOperator(array $days, string $regime): array
    {
        $start     = Carbon::parse($days[0])->startOfDay();
        $end       = Carbon::parse(end($days))->endOfDay();
        $operators = $this->getOperators($regime);
        $flights   = $this->fetchFlights($start, $end, $regime);

        $isInt = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($operators as $op) {
            $opFlights = $flights->where('operator_id', $op->id);

            foreach ($days as $day) {
                $dayFlights = $opFlights->filter(
                    fn($f) => Carbon::parse($f->departure_time)->format('Y-m-d') === $day
                );

                $stats = $this->aggregate($dayFlights, $regime);
                $label = ['DATE' => Carbon::parse($day)->format('d/m/Y')];

                $pax[$op->sigle][]      = $label + $this->paxRow($stats);
                $fretDep[$op->sigle][]  = $label + ['traffic' => $stats['fret_dep'],  'idef' => $stats['idef_fret_dep']];
                $excedDep[$op->sigle][] = $label + ['traffic' => $stats['exced_dep'], 'idef' => $stats['idef_exced_dep']];

                if ($isInt) {
                    $fretArr[$op->sigle][]  = $label + ['traffic' => $stats['fret_arr'],  'idef' => $stats['idef_fret_arr']];
                    $excedArr[$op->sigle][] = $label + ['traffic' => $stats['exced_arr'], 'idef' => $stats['idef_exced_arr']];
                }
            }
        }

        return $this->shapeResult($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // ANNUAL BUILDERS — BY OPERATOR
    // =========================================================================

    private function buildAnnualByOperator(array $months, int $year, string $regime): array
    {
        $start     = Carbon::create($year, 1, 1)->startOfYear();
        $end       = Carbon::create($year, 12, 31)->endOfYear();
        $operators = $this->getOperators($regime);
        $flights   = $this->fetchFlights($start, $end, $regime);

        $isInt = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $pax = $fretDep = $fretArr = $excedDep = $excedArr = [];

        foreach ($operators as $op) {
            $opFlights = $flights->where('operator_id', $op->id);

            foreach ($months as $month) {
                $monthFlights = $opFlights->filter(function ($f) use ($month, $year) {
                    $d = Carbon::parse($f->departure_time);
                    return $d->month === $month && $d->year === $year;
                });

                $stats = $this->aggregate($monthFlights, $regime);
                $label = ['MOIS' => Carbon::create($year, $month, 1)->format('m-Y')];

                $pax[$op->sigle][]      = $label + $this->paxRow($stats);
                $fretDep[$op->sigle][]  = $label + ['traffic' => $stats['fret_dep'],  'idef' => $stats['idef_fret_dep']];
                $excedDep[$op->sigle][] = $label + ['traffic' => $stats['exced_dep'], 'idef' => $stats['idef_exced_dep']];

                if ($isInt) {
                    $fretArr[$op->sigle][]  = $label + ['traffic' => $stats['fret_arr'],  'idef' => $stats['idef_fret_arr']];
                    $excedArr[$op->sigle][] = $label + ['traffic' => $stats['exced_arr'], 'idef' => $stats['idef_exced_arr']];
                }
            }
        }

        return $this->shapeResult($isInt, $pax, $fretDep, $excedDep, $fretArr, $excedArr);
    }

    // =========================================================================
    // AGGREGATION HELPERS
    // =========================================================================

    /**
     * Fetch all relevant flights for a date range and regime in one query.
     * The relation `statistic` is eager-loaded to avoid N+1 issues.
     */
    private function fetchFlights(Carbon $start, Carbon $end, string $regime): Collection
    {
        return Flight::with('statistic')
            ->where('flight_regime', $regime)
            ->whereBetween('departure_time', [$start, $end])
            ->where('status', FlightStatusEnum::DEPARTED)
            ->get();
    }

    /**
     * Aggregate statistics from a collection of flights for a given regime.
     *
     * Returns a flat array with all metrics needed:
     *   pax, gopass, paxbus, justifications,
     *   fret_dep, fret_arr (int only for domestic),
     *   idef_fret_dep, idef_fret_arr,
     *   exced_dep, exced_arr,
     *   idef_exced_dep, idef_exced_arr
     *
     * The IDEF columns correspond to non-UN (non-exonerated) flights.
     * UN operator flights count toward traffic but not toward IDEF.
     */
    private function aggregate(Collection $flights, string $regime): array
    {
        $isInt = $regime === FlightRegimeEnum::INTERNATIONAL->value;

        $stats = [
            'pax'           => 0,
            'gopass'        => 0,
            'paxbus'        => 0,
            'justifications'=> [],
            'fret_dep'      => 0,
            'fret_arr'      => 0,
            'idef_fret_dep' => 0,
            'idef_fret_arr' => 0,
            'exced_dep'     => 0,
            'exced_arr'     => 0,
            'idef_exced_dep'=> 0,
            'idef_exced_arr'=> 0,
        ];

        foreach ($flights as $flight) {
            $stat = $flight->statistic;
            if (!$stat) continue;

            $isUN = $flight->operator?->sigle === 'UN';

            $stats['pax']    += (int) ($stat->passengers_count ?? 0);
            $stats['gopass'] += (int) ($stat->go_pass_count    ?? 0);
            $stats['paxbus'] += (int) ($stat->pax_bus          ?? 0);

            // Freight
            $fretDep = (int) ($stat->fret_count['departure'] ?? 0);
            $fretArr = (int) ($stat->fret_count['arrival']   ?? 0);
            $stats['fret_dep'] += $fretDep;
            $stats['fret_arr'] += $fretArr;
            if (!$isUN) {
                $stats['idef_fret_dep'] += $fretDep;
                $stats['idef_fret_arr'] += $fretArr;
            }

            // Excedents
            $excedDep = (int) ($stat->excedents['departure'] ?? 0);
            $excedArr = (int) ($stat->excedents['arrival']   ?? 0);
            $stats['exced_dep'] += $excedDep;
            $stats['exced_arr'] += $excedArr;
            if (!$isUN) {
                $stats['idef_exced_dep'] += $excedDep;
                $stats['idef_exced_arr'] += $excedArr;
            }

            // Justifications (PAX ecart)
            if ($stat->has_justification && is_array($stat->justification)) {
                foreach ($stat->justification as $key => $value) {
                    if (is_array($value)) {
                        $stats['justifications'][$key] ??= [];
                        foreach ($value as $subKey => $subVal) {
                            $stats['justifications'][$key][$subKey] = ($stats['justifications'][$key][$subKey] ?? 0) + (int) $subVal;
                        }
                    } else {
                        $stats['justifications'][$key] = ($stats['justifications'][$key] ?? 0) + (int) $value;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Build the PAX sub-array for a row.
     */
    private function paxRow(array $stats): array
    {
        return [
            'traffic'        => $stats['pax'],
            'gopass'         => $stats['gopass'],
            'paxbus'         => $stats['paxbus'],
            'justifications' => $stats['justifications'],
        ];
    }

    /**
     * Shape the final regime result depending on whether it is domestic or
     * international (different metric keys are expected by the export sheets).
     */
    private function shapeResult(
        bool  $isInt,
        array $pax,
        array $fretDep,
        array $excedDep,
        array $fretArr  = [],
        array $excedArr = []
    ): array {
        if ($isInt) {
            return [
                'pax'           => $pax,
                'fret_depart'   => $fretDep,
                'fret_arrivee'  => $fretArr,
                'exced_depart'  => $excedDep,
                'exced_arrivee' => $excedArr,
            ];
        }

        return [
            'pax'       => $pax,
            'fret'      => $fretDep,
            'excedents' => $excedDep,
        ];
    }

    // =========================================================================
    // IDEF FRET HELPERS
    // =========================================================================

    /**
     * Return raw IDEF fret entries keyed by day for a month.
     * Every calendar day appears even if no entry exists (defaults to 0).
     */
    private function idefFretByDays(int $month, int $year): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        $keyed = $this->idefFretService
            ->getByDateRange($start->format('Y-m-d'), $end->format('Y-m-d'))
            ->keyBy(fn($f) => Carbon::parse($f->date)->format('Y-m-d'));

        return collect($this->getDaysOfMonth($month, $year))
            ->map(function ($day) use ($keyed) {
                $entry = $keyed->get($day);
                return [
                    'DATE' => Carbon::parse($day)->format('d/m/Y'),
                    'usd'  => $entry?->usd ?? 0,
                    'cdf'  => $entry?->cdf ?? 0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Return IDEF fret entries aggregated by month for a full year.
     * Each row also carries the exchange rate for that month.
     */
    private function idefFretByMonths(array $months, int $year): array
    {
        return collect($months)->map(function ($month) use ($year) {
            $days = $this->idefFretByDays($month, $year);
            $rate = $this->monthlyRateService->findByMonth((string) $month, (string) $year)?->rate ?? 0;

            return [
                'MOIS' => Carbon::create($year, $month, 1)->format('m-Y'),
                'usd'  => collect($days)->sum('usd'),
                'cdf'  => collect($days)->sum('cdf'),
                'rate' => $rate,
            ];
        })->toArray();
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    /**
     * Check whether flight data exists for the given period and any regime.
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
     * Return all operators that have departed flights for the given regime.
     */
    private function getOperators(string $regime): Collection
    {
        return Operator::with('aircrafts')
            ->whereHas('flights', fn($q) => $q
                ->where('flight_regime', $regime)
                ->where('status', FlightStatusEnum::DEPARTED)
            )
            ->orderBy('sigle')
            ->get();
    }

    /**
     * Return an array of YYYY-MM-DD strings for every day of the given month.
     */
    private function getDaysOfMonth(int $month, int $year): array
    {
        return collect(range(1, Carbon::create($year, $month, 1)->daysInMonth))
            ->map(fn($d) => Carbon::create($year, $month, $d)->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Return the French name of a month number.
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