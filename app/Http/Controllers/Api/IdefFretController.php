<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\IdefFret;
use App\Services\IdefFretServiceInterface;
use Illuminate\Http\Request;

/**
 * @group IdefFretManagement
 *
 * Endpoints related to IDE Fret and monthly exchange rates management.
 */
class IdefFretController extends Controller
{
    public function __construct(
        protected IdefFretServiceInterface $idefFretService,
    ) {}

    /**
     * Store a new idef fret entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:idef_frets,date',
            'usd' => 'required|numeric|min:0',
            'cdf' => 'required|numeric|min:0',
        ]);

        $ideFret = $this->idefFretService->createIdefFret($validated);
        return response()->json($ideFret, 201);
    }

    /**
     * Store or update multiple idef fret entries in batch.
     *
     * Accepts an array of entries. Each entry with a date that already exists
     * will be updated; entries with new dates will be created.
     *
     * Body example:
     * {
     *   "entries": [
     *     { "date": "2026-02-01", "usd": 120, "cdf": 0 },
     *     { "date": "2026-02-02", "usd": 95,  "cdf": 500 }
     *   ]
     * }
     */
    public function storeBatch(Request $request)
    {
        $request->validate([
            'entries'             => 'required|array|min:1',
            'entries.*.date'      => 'required|date',
            'entries.*.usd'       => 'required|numeric|min:0',
            'entries.*.cdf'       => 'required|numeric|min:0',
        ]);

        $result = $this->idefFretService->upsertBatch($request->input('entries'));

        return response()->json($result, 200);
    }

    /**
     * Update an idef fret entry.
     */
    public function update(Request $request, IdefFret $idefFret)
    {
        $validated = $request->validate([
            'date' => 'date|unique:idef_frets,date,' . $idefFret->id,
            'usd' => 'numeric|min:0',
            'cdf' => 'numeric|min:0',
        ]);

        $updated = $this->idefFretService->updateIdefFret($idefFret, $validated);
        return response()->json($updated);
    }

    /**
     * Delete an idef fret entry.
     */
    public function destroy(IdefFret $idefFret)
    {
        $this->idefFretService->deleteIdefFret($idefFret);
        return response()->noContent();
    }

    /**
     * Get idef fret entry by date.
     */
    public function getIdefFretByDate($date)
    {
        $ideFret = $this->idefFretService->findByDate($date);
        return $ideFret
            ? response()->json($ideFret)
            : ApiResponse::error('IdefFret not found', 404);
    }

    /**
     * Get idef frets by date range.
     */
    public function getIdefFretsByRange($from, $to)
    {
        $ideFrets = $this->idefFretService->getByDateRange($from, $to);

        return $ideFrets
            ? response()->json($ideFrets)
            : ApiResponse::error('IdefFrets not found', 404);
    }
}   