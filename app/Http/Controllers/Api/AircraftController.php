<?php

namespace App\Http\Controllers\Api;

use App\Models\Aircraft;
use Illuminate\Support\Js;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
    public function index()
    {
        // Retrieve all aircrafts
        $aircrafts = $this->service->getAll();

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
        return new AircraftResource($aircraft);
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
     * Search for an aircraft by its immatriculation.
     *
     * This endpoint searches for an aircraft by its immatriculation.
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
     */
    public function search(Request $request)
    {
        /**
         * The immatriculation to search.
         *
         * @var string
         */
        $term = $request->get('term');

        /**
         * The searched aircraft.
         *
         * @var Aircraft|null
         */
        $aircraft = $this->service->findByImmatriculation($term);

        // Return the searched aircraft as a JSON response
        return $aircraft ? new AircraftResource($aircraft) : ApiResponse::error('Aircraft not found', 404);
    }

    /**
     * List aircrafts by operator.
     *
     * This endpoint returns a list of aircrafts by the operator they belong to.
     *
     * @api {get} /aircrafts/by-operator/{operator}
     * @apiName List aircrafts by operator
     * @apiGroup Aircrafts
     * @apiParam {int} operator required The operator id. Example: 1
     * @apiSuccessResponse {json} The list of aircrafts
     * @response 200 OK
     * @responseContent json
     */
    public function byOperator(int $operatorId)
    {
        /**
         * The list of aircrafts.
         *
         * @var Collection
         */
        $aircrafts = $this->service->findByOperator($operatorId);

        // Return the list of aircrafts as a JSON response
        return AircraftResource::collection($aircrafts);
    }
}