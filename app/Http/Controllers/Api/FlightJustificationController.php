<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Models\FlightJustification;
use App\Http\Controllers\Controller;
use App\Services\FlightJustificationService;
use App\Http\Resources\FlightJustificationResource;
use App\Http\Requests\StoreFlightJustificationRequest;
use App\Http\Requests\UpdateFlightJustificationRequest;

class FlightJustificationController extends Controller
{
    public function __construct(protected FlightJustificationService $service) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Retrieve all flight justifications
        $flightJustifications = $this->service->getAll();

        // Return the response
        return FlightJustificationResource::collection($flightJustifications);
    }

    /**
     * Store a new flight justification in the database.
     *
     * Retrieves the request data, validates it, and stores a new flight justification in the database.
     * Returns a JSON response containing the newly created flight justification.
     *
     * @param StoreFlightJustificationRequest $request The validated request data
     * @return \Illuminate\Http\JsonResponse The newly created flight justification
     */
    public function store(StoreFlightJustificationRequest $request)
    {
        // Retrieve the request data
        $validatedData = $request->validated();

        // Create a new flight justification from the request data
        $justification = $this->service->create($validatedData);

        // Return the response
        return ApiResponse::success(new FlightJustificationResource($justification), 'Créé avec succès', 201);
    }

    /**
     * Update a flight justification from the database.
     *
     * @param UpdateFlightJustificationRequest $request The HTTP request
     * @param FlightJustification $flightJustification The flight justification to update
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateFlightJustificationRequest $request, FlightJustification $flightJustification)
    {
        // Update the flight justification
        $justification = $this->service->update($flightJustification, $request->validated());

        // Return the response
        return ApiResponse::success(new FlightJustificationResource($justification), 'Mis à jour avec succès', 200);
    }

    /**
     * Delete a flight justification from the database.
     *
     * @param FlightJustification $flightJustification The flight justification to delete
     * @return \Illuminate\Http\Response
     */
    public function destroy(FlightJustification $flightJustification)
    {
        // Delete the flight justification from the database
        $this->service->delete($flightJustification);
        
        // Return a successful response with no content
        return ApiResponse::success(null, 'Supprimé avec succès');
    }
}
