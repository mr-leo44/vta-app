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

    /** Display all aircrafts */
    public function index() { return response()->json($this->service->getAll()); }

    /** Store a new aircraft */
    public function store(StoreAircraftRequest $request)
    {
        return response()->json($this->service->store($request->validated()), 201);
    }

    /** Update an aircraft */
    public function update(UpdateAircraftRequest $request, Aircraft $aircraft)
    {
        return response()->json($this->service->update($aircraft, $request->validated()));
    }

    /** Delete an aircraft */
    public function destroy(Aircraft $aircraft)
    {
        $this->service->delete($aircraft);
        return response()->noContent();
    }

    /** Search by immatriculation */
    public function search(Request $request)
    {
        $aircraft = $this->service->findByImmatriculation($request->get('term'));
        return response()->json($aircraft ?: []);
    }

    /** List all aircrafts by operator */
    public function byOperator(int $operatorId)
    {
        return response()->json($this->service->findByOperator($operatorId));
    }
}

