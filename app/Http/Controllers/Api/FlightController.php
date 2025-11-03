<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightStoreRequest;
use App\Http\Requests\FlightUpdateRequest;
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $flights = $this->flightService->list($request->all());
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
}