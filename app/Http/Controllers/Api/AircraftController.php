<?php

namespace App\Http\Controllers\Api;

use App\Models\Aircraft;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\AircraftResource;
use App\Services\AircraftServiceInterface;
use App\Http\Requests\StoreAircraftRequest;
use App\Http\Requests\UpdateAircraftRequest;

/**
 * @group Aircrafts
 *
 * Endpoints to manage aircrafts.
 */
class AircraftController extends Controller
{
    public function __construct(protected AircraftServiceInterface $service) {}

    /**
     * Get all aircrafts.
     *
     * Retrieves all aircrafts from the database.
     *
     * @return \Illuminate\Http\JsonResponse
     * @response 200 OK
     * @responseContent json
     */
    public function all()
    {
        // Retrieve all aircrafts
        $aircrafts = $this->service->getAll();

        // Return the response
        return AircraftResource::collection($aircrafts);
    }

    /**
     * Get all paginated aircrafts.
     *
     * Retrieves all aircrafts from the database.
     *
     * @return \Illuminate\Http\JsonResponse
     * @response 200 OK
     * @responseContent json
     */
    public function index()
    {
        // Retrieve all paginated aircrafts
        $aircrafts = $this->service->getAllPaginated();

        // Return the response
        return AircraftResource::collection($aircrafts);
    }

    /**
     * Store a new aircraft
     *
     * Retrieves the request data, validates it, and stores a new aircraft in the database.
     * Returns a JSON response containing the newly created aircraft.
     *
     * @api {post} /aircrafts
     * @apiName Store a new aircraft
     * @apiGroup Aircrafts
     * @apiParam {string} immatriculation The immatriculation (immatriculation number) of the aircraft
     * @apiParam {string} pmad The purchase made at date of the aircraft
     * @apiParam {boolean} in_activity Whether the aircraft is currently in use
     * @apiParam {integer} aircraft_type_id The ID of the aircraft type
     * @apiParam {integer} operator_id The ID of the operator
     * @apiSuccessResponse {json} The newly created aircraft
     * @response 201 Created
     * @responseContent json
     */
    public function store(StoreAircraftRequest $request)
    {
        // Retrieve the request data
        $validatedData = $request->validated();

        // Store the new aircraft in the database
        $aircraft = $this->service->store($validatedData);

        // Return the response
        return new AircraftResource($aircraft);
    }

     /**
     * Retrieves an aircraft by ID.
     *
     * @param Aircraft $aircraft
     * @return \Illuminate\Http\JsonResponse
     * @response 200 OK
     * @responseContent json
     */
    public function show(Aircraft $aircraft)
    {
        // Return the aircraft as a JSON response
        $aircraftData = Aircraft::with(['flights', 'operator', 'type'])->find($aircraft['id']);
        return new AircraftResource($aircraftData);
    }

    /**
     * Update an existing aircraft.
     *
     * This endpoint updates an existing aircraft in the database.
     *
     * @api {put} /aircrafts/{aircraft}
     * @apiName Update an aircraft
     * @apiGroup Aircrafts
     * @apiParam {string} immatriculation The immatriculation (immatriculation number) of the aircraft
     * @apiParam {string} pmad The purchase made at date of the aircraft
     * @apiParam {boolean} in_activity Whether the aircraft is currently in use
     * @apiParam {integer} aircraft_type_id The ID of the aircraft type
     * @apiParam {integer} operator_id The ID of the operator
     * @apiSuccessResponse {json} The updated aircraft
     * @response 200 OK
     * @responseContent json
     */
    public function update(UpdateAircraftRequest $request, Aircraft $aircraft)
    {
        // Retrieve the validated data from the request
        $validatedData = $request->validated();

        // Update the aircraft in the database
        $updatedAircraft = $this->service->update($aircraft, $validatedData);

        // Return the updated aircraft as a JSON response
        return new AircraftResource($updatedAircraft);
    }
    /**
     * Delete an aircraft from the database.
     *
     * This endpoint deletes an existing aircraft from the database.
     *
     * @api {delete} /aircrafts/{aircraft}
     * @apiName Delete an aircraft
     * @apiGroup Aircrafts
     * @apiParam {integer} aircraft The ID of the aircraft
     * @apiSuccessResponse {json} No response
     * @response 204 No Content
     */
    public function destroy(Aircraft $aircraft)
    {
        // Delete the aircraft from the database
        $this->service->delete($aircraft);
        
        // Return a successful response with no content
        return ApiResponse::success(null, 'Aircraft deleted successfully');
    }

    /**
     * Search for an aircraft by its immatriculation, operator or type.
     *
     * This endpoint searches for an aircraft by its immatriculation, operator or type.
     *
     * @api {get} /aircrafts/search?term={term}
     * @apiName Search for an aircraft by immatriculation
     * @apiGroup Aircrafts
     * @apiParam {string} term The immatriculation to search. Example: 9Q-ABC
     * @apiSuccessResponse {json} The searched aircraft
     * @response 200 OK
     * @responseContent json
     * @apiErrorExample {json} Aircraft not found
     * @response 404 Not Found
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Search for an aircraft by its immatriculation, operator or type.
     *
     * @param Request $request The request object containing the search term
     *
     * @return \Illuminate\Http\JsonResponse The searched aircraft as a JSON response
     */
    public function search(Request $request)
    {
        /**
         * The term to search.
         *
         * @var string
         */
        $term = $request->get('term');

        /**
         * The searched aircraft.
         *
         * @var Aircraft|null
         */
        $aircrafts = $this->service->search($term);

        // Return the searched aircraft as a JSON response
        return $aircrafts
            ? AircraftResource::collection($aircrafts)
            : ApiResponse::error('Aéronef non trouvé', 404);
    }
}