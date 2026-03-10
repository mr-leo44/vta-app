<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MonthlyRate;
use App\Services\MonthlyRateServiceInterface;
use Illuminate\Http\Request;

/**
 * @group MonthlyRateManagement
 *
 * Endpoints related to IDE Fret and monthly exchange rates management.
 */
class MonthlyRateController extends Controller
{
    public function __construct(
        protected MonthlyRateServiceInterface $monthlyRateService,
    ) {}

    /**
     * Get monthly rates.
     */
    public function index()
    {
        $monthlyRates = $this->monthlyRateService->getAllMonthlyRates();
        return $monthlyRates
            ? response()->json($monthlyRates)
            : ApiResponse::error('MonthlyRates not found', 404);
    }

    /**
     * Store a new monthly rate entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|date|unique:monthly_rates,month',
            'rate' => 'required|numeric|min:0',
        ]);

        $monthlyRate = $this->monthlyRateService->createMonthlyRate($validated);
        return response()->json($monthlyRate, 201);
    }

    /**
     * Update an monthly rate entry.
     */
    public function update(Request $request, MonthlyRate $monthlyRate)
    {
        $validated = $request->validate([
            'month' => 'date|unique:monthly_rates,month,' . $monthlyRate->id,
            'rate' => 'numeric|min:0',
        ]);

        $updated = $this->monthlyRateService->updateMonthlyRate($monthlyRate, $validated);
        return response()->json($updated);
    }

    /**
     * Delete an monthly rate entry.
     */
    public function destroy(MonthlyRate $monthlyRate)
    {
        $this->monthlyRateService->deleteMonthlyRate($monthlyRate);
        return response()->noContent();
    }

    /**
     * Get monthly rate entry by month.
     */
    public function getMonthlyRateByMonth($month)
    {
        $monthlyRate = $this->monthlyRateService->findByMonth($month);
        return $monthlyRate
            ? response()->json($monthlyRate)
            : ApiResponse::error('MonthlyRate not found', 404);
    }
}
