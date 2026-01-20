<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightStoreRequest;
use App\Http\Requests\FlightUpdateRequest;
use App\Http\Requests\FilterFlightRequest;
use App\Http\Resources\FlightResource;
use App\Models\Flight;
use App\Services\FlightService;
use Illuminate\Http\Request;

/**
 * @group Flights
 *
 * Endpoints related to flights.
 */
class FlightController extends Controller
{
    /**
     * @param private FlightService $flightService
     */
    public function __construct(private FlightService $flightService) {}

    /**
     * Get all flights
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function all()
    {
        // Get all flights
        $flights = $this->flightService->list();

        // Return the flights as a JSON response
        return FlightResource::collection($flights);
    }

    /**
     * Get all flights with optional filters
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get all flights with optional filters
        $flights = $this->flightService->listPaginated($request->all());

        // Return the flights as a JSON response
        return FlightResource::collection($flights);
    }

    /**
     * Store a new flight
     * 
     * @param FlightStoreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(FlightStoreRequest $request)
    {
        $flight = $this->flightService->store($request->validated());
        return new FlightResource($flight);
    }

    /**
     * Show a specific flight
     * 
     * @param Flight $flight
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Flight $flight)
    {
        return new FlightResource($this->flightService->show($flight->id));
    }

    /**
     * Update an existing flight
     * 
     * @param FlightUpdateRequest $request
     * @param Flight $flight
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(FlightUpdateRequest $request, Flight $flight)
    {
        $flight = $this->flightService->update($flight, $request->validated());
        return new FlightResource($flight);
    }

    /**
     * Delete a flight
     * 
     * @param Flight $flight
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Flight $flight)
    {
        $this->flightService->delete($flight);
        return response()->json(['message' => 'Flight deleted successfully']);
    }

    /**
     * Get all flights for a given date
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function flightsByDate(Request $request)
    {
        // Get the date from the request query
        // Format: YYYY-MM-DD
        $date = $request->query('date');
        // Get all flights for the given date
        // Latest flights first
        $flights = Flight::with(['statistic'])->whereDate('departure_time', $date)
            ->orderBy('departure_time', 'asc')
            ->get();

        // Return the flights as JSON
        return FlightResource::collection($flights);
    }

    /**
     * Filter flights with advanced criteria.
     *
     * @queryParam search string Search in flight number or airports. Example: AF
     * @queryParam status string Filter by flight status. Example: Completed
     * @queryParam flight_regime string Filter by flight regime. Example: Domestic
     * @queryParam flight_type string Filter by flight type. Example: Regular
     * @queryParam operator_id integer Filter by operator ID. Example: 1
     * @queryParam aircraft_id integer Filter by aircraft ID. Example: 2
     * @queryParam departure_date_from date Start date range. Example: 2026-01-01
     * @queryParam departure_date_to date End date range. Example: 2026-01-31
     * @queryParam sort string The sort order. Example: departure_time:desc
     * @queryParam per_page integer Items per page. Example: 20
     */
    public function filter(FilterFlightRequest $request)
    {
        $filters = $request->getFilters();
        $flights = $this->flightService->filter($filters);
        return FlightResource::collection($flights);
    }
}
