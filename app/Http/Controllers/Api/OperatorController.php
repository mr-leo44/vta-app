<?php

namespace App\Http\Controllers\Api;

use App\Models\Operator;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OperatorResource;
use App\Services\OperatorServiceInterface;
use App\Http\Requests\StoreOperatorRequest;
use App\Http\Requests\UpdateOperatorRequest;

/**
 * @group Operators
 *
 * Endpoints related to flight operators (airlines).
 */
class OperatorController extends Controller
{
    public function __construct(
        protected OperatorServiceInterface $service
    ) {}

    /**
     * Display all operators.
     */
    public function index()
    {
        $operators = $this->service->getAll();
        return OperatorResource::collection($operators);
    }

    /**
     * Store a new operator.
     */
    public function store(StoreOperatorRequest $request)
    {
        $operator = $this->service->store($request->validated());
        return new OperatorResource($operator);
    }

    /**
     * Display an operator.
     */
    public function show(Operator $operator)
    {
        return new OperatorResource($operator);
    }

    /**
     * Update an existing operator.
     */
    public function update(UpdateOperatorRequest $request, Operator $operator)
    {
        $updated = $this->service->update($operator, $request->validated());
        return new OperatorResource($updated);
    }

    /**
     * Delete an operator.
     */
    public function destroy(Operator $operator)
    {
        $this->service->delete($operator);
        return ApiResponse::success(null, 'Operator deleted successfully');
    }

    /**
     * Find operator by name or IATA code.
     *
     * @queryParam term string required The search term (operator name or IATA code). Example: CAA
     */
    public function search(Request $request)
    {
        $term = $request->get('term');
        $operator = $this->service->findByNameOrIata($term);
        return $operator
            ? new OperatorResource($operator)
            : ApiResponse::error('Exploitant non trouvé', 404);
    }
}
