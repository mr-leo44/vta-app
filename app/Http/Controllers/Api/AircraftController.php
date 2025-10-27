<?php

namespace App\Http\Controllers\Api;

use App\Models\Aircraft;
use Illuminate\Support\Js;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
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
     * Display all aircrafts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        /**
         * Get all aircrafts.
         *
         * @return \Illuminate\Http\JsonResponse
         */
        return response()->json($this->service->getAll());
    }

    /**
     * Store a new aircraft
     *
     * @api {post} /aircrafts
     * @apiName Store a new aircraft
     * @apiGroup Aircrafts
     * @apiParam {string} immatriculation The immatriculation (registration number) of the aircraft
     * @apiParam {string} pmad The purchase made at date of the aircraft
     * @apiParam {boolean} in_activity Whether the aircraft is currently in use
     * @apiParam {integer} aircraft_type_id The ID of the aircraft type
     * @apiParam {integer} operator_id The ID of the operator
     * @apiSuccessResponse {json} The newly created aircraft
     */
    public function store(StoreAircraftRequest $request)
    {
        /**
         * Store a new aircraft
         *
         * @param StoreAircraftRequest $request The request object
         * @return \Illuminate\Http\JsonResponse The newly created aircraft
         */
        return response()->json($this->service->store($request->validated()), 201);
    }

    /**
     * Update an existing aircraft.
     *
     * @api {put} /aircrafts/{aircraft}
     * @apiName Update an aircraft
     * @apiGroup Aircrafts
     * @apiParam {string} immatriculation The immatriculation (registration number) of the aircraft
     * @apiParam {string} pmad The purchase made at date of the aircraft
     * @apiParam {boolean} in_activity Whether the aircraft is currently in use
     * @apiParam {integer} aircraft_type_id The ID of the aircraft type
     * @apiParam {integer} operator_id The ID of the operator
     * @apiSuccessResponse {json} The updated aircraft
     */
    public function update(UpdateAircraftRequest $request, Aircraft $aircraft)
    {
        /**
         * Update an existing aircraft.
         *
         * @param UpdateAircraftRequest $request The request object
         * @param Aircraft $aircraft The aircraft to update
         * @return \Illuminate\Http\JsonResponse The updated aircraft
         */
        return response()->json($this->service->update($aircraft, $request->validated()));
    }
    /**
     * Delete an aircraft.
     *
     * @api {delete} /aircrafts/{aircraft}
     * @apiName Delete an aircraft
     * @apiGroup Aircrafts
     * @apiParam {integer} aircraft The ID of the aircraft to delete
     * @apiSuccessResponse {json} Empty response
     */
    public function destroy(Aircraft $aircraft)
    {
        /**
         * Delete an aircraft.
         *
         * @param Aircraft $aircraft The aircraft to delete
         * @return \Illuminate\Http\JsonResponse Empty response
         */
        $this->service->delete($aircraft);
        return response()->noContent();
    }

    /**
     * Search for an aircraft by immatriculation.
     *
     * @api {get} /aircrafts/search?term={term}
     * @apiName Search an aircraft by immatriculation
     * @apiGroup Aircrafts
     * @apiParam {string} term The immatriculation (registration number) to search for
     * @apiSuccessResponse {json} The aircraft with the given immatriculation, or an empty array if none found
     */
    public function search(Request $request)
    {
        /**
         * Search for an aircraft by immatriculation.
         *
         * @param Request $request The request object
         * @return \Illuminate\Http\JsonResponse The aircraft with the given immatriculation, or an empty array if none found
         */
        $aircraft = $this->service->findByImmatriculation($request->get('term'));
        return response()->json($aircraft ?: []);
    }
    /**
     * List all aircrafts by operator.
     *
     * @param int $operatorId The ID of the operator to search for
     * @return \Illuminate\Http\JsonResponse The aircrafts belonging to the operator, or an empty array if none found
     * @api {get} /aircrafts/operator/{operatorId}
     * @apiName List all aircrafts by operator
     * @apiGroup Aircrafts
     */
    public function byOperator(int $operatorId)
    {
        // Get all aircrafts that belong to the given operator
        $aircrafts = $this->service->findByOperator($operatorId);

        // Return the aircrafts in JSON format
        return response()->json($aircrafts ?: []);
    }
}