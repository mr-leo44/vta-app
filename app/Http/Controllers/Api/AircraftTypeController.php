<?php

namespace App\Http\Controllers\Api;

use App\Models\AircraftType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\AircraftTypeServiceInterface;

/**
 * @group AircraftTypes
 *
 * Endpoints to manage aircraft types.
 */
class AircraftTypeController extends Controller
{
    public function __construct(protected AircraftTypeServiceInterface $service) {}

    /**
     * @api {get} /aircraft-types
     * @apiName List all aircraft types
     * @apiGroup AircraftTypes
     * @apiSuccessResponse {json} Collection of aircraft types
     */
    /** Display all aircraft types */
    public function index()
    {
        /**
         * Get all aircraft types
         *
         * @return \Illuminate\Http\JsonResponse
         */
        return response()->json($this->service->getAll());
    }

    /**
     * Store a new aircraft type
     *
     * @api {post} /aircraft-types
     * @apiName Store a new aircraft type
     * @apiGroup AircraftTypes
     * @apiParam {string} name The name of the aircraft type
     * @apiParam {string} sigle The sigle of the aircraft type
     * @apiSuccessResponse {json} The newly created aircraft type
     */
    public function store(Request $request)
    {
        /**
         * Validate the request data
         *
         * @param Request $request The HTTP request
         * @return array The validated data
         */
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:aircraft_types,name',
            'sigle' => 'required|string|max:10|unique:aircraft_types,sigle',
        ]);
        /**
         * Store the aircraft type
         *
         * @param array $validated The validated data
         * @return \Illuminate\Http\JsonResponse The newly created aircraft type
         */
        return response()->json($this->service->store($validated), 201);
    }

    /**
     * Update an aircraft type
     *
     * @api {put} /aircraft-types/{aircraft-type}
     * @apiName Update an aircraft type
     * @apiGroup AircraftTypes
     * @apiParam {int} aircraft-type The ID of the aircraft type
     * @apiParam {string} name The name of the aircraft type
     * @apiParam {string} sigle The sigle of the aircraft type
     * @apiSuccessResponse {json} The updated aircraft type
     */
    public function update(Request $request, AircraftType $aircraftType)
    {
        /**
         * Validate the request data
         *
         * @param Request $request The HTTP request
         * @return array The validated data
         */
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:aircraft_types,name,' . $aircraftType->id,
            'sigle' => 'sometimes|required|string|max:10|unique:aircraft_types,sigle,' . $aircraftType->id,
        ]);

        /**
         * Update the aircraft type
         *
         * @param array $validated The validated data
         * @return \Illuminate\Http\JsonResponse The updated aircraft type
         */
        return response()->json($this->service->update($aircraftType, $validated));
    }        
/*************  ✨ Windsurf Command 🌟  *************/
    /**
     * Delete an aircraft type
     *
     * @api {delete} /aircraft-types/{aircraft-type}
     * @apiName Delete an aircraft type
     * @apiGroup AircraftTypes
     * @apiParam {int} aircraft-type The ID of the aircraft type
     * @apiSuccessResponse {json} No response
     */
    /** Delete an aircraft */
    public function destroy(AircraftType $aircraftType)
    {
        /**
         * Delete the aircraft type
         *
         * @param AircraftType $aircraftType The aircraft type to delete
         * @return \Illuminate\Http\Response No response
         */
        $this->service->delete($aircraftType);
        return response()->noContent();
    }
    /**
     * List all aircrafts by name or sigle
     *
     * @param string $query The search query
     * @return \Illuminate\Http\JsonResponse The list of aircrafts
     * @apiName Find aircrafts by name or sigle
     * @apiGroup AircraftTypes
     * @apiParam {string} query The search query
     * @apiSuccessResponse {json} The list of aircrafts
     */ 
    public function find(string $query)
    {
        /**
         * Search for aircrafts by name or sigle
         *
         * @param string $query The search query
         * @return \Illuminate\Http\JsonResponse The list of aircrafts
         */
        return response()->json($this->service->find($query));
    }
}
