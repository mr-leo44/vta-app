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
use App\Http\Requests\FilterOperatorRequest;

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
     * Display all paginated operators.
     */
    public function index()
    {
        $operators = $this->service->getAllPaginated();
        return OperatorResource::collection($operators);
    }

     /**
     * Display all operators.
     */
    public function all()
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
        return new OperatorResource($operator->load(['flights', 'aircrafts.type']));
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
        return response()->noContent();
    }

    /**
     * Find operators by name or IATA code.
     *
     * @queryParam term string required The search term (operator name or IATA code). Example: CAA
     */
    public function search(Request $request)
    {
        $term = $request->get('term');
        $operators = $this->service->findByNameOrIata($term);
        return $operators
            ? OperatorResource::collection($operators)
            : ApiResponse::error('Exploitant non trouvé', 404);
    }

    /**
     * Filter operators with advanced criteria.
     *
     * @queryParam search string The search term (name/IATA/ICAO). Example: Air
     * @queryParam country string The country to filter by. Example: DRC
     * @queryParam flight_type string The flight type (regular/non_regular). Example: regular
     * @queryParam flight_regime string The regime (domestic/international). Example: domestic
     * @queryParam flight_nature string The nature (commercial/non_commercial). Example: commercial
     * @queryParam sort string The sort order. Example: name:asc
     * @queryParam per_page integer Items per page. Example: 20
     */
    public function filter(FilterOperatorRequest $request)
    {
        $filters = $request->getFilters();
        $operators = $this->service->filter($filters);
        return OperatorResource::collection($operators);
    }
}
