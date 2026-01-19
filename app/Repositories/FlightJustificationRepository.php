<?php

namespace App\Repositories;

use App\Models\FlightJustification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for repositories that handle FlightJustification models.
 */
interface FlightJustificationRepositoryInterface
{
    /**
     * Retrieve all FlightJustification models.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(): Collection;

    /**
     * Create a new FlightJustification model.
     *
     * @param array $data
     * @return \App\Models\FlightJustification
     */
    public function create(array $data): FlightJustification;

    /**
     * Update a FlightJustification model.
     *
     * @param \App\Models\FlightJustification $justification
     * @param array $data
     * @return \App\Models\FlightJustification
     */
    public function update(FlightJustification $justification, array $data): FlightJustification;

    /**
     * Delete a FlightJustification model.
     *
     * @param \App\Models\FlightJustification $justification
     */
    public function delete(FlightJustification $justification): void;
}


/**
 * Repository for FlightJustification models.
 */
class FlightJustificationRepository implements FlightJustificationRepositoryInterface
{
    /**
     * Retrieve all FlightJustification models.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(): Collection
    {
        return FlightJustification::latest()->get();
    }

    /**
     * Create a new FlightJustification model.
     *
     * @param array $data
     * @return \App\Models\FlightJustification
     */
    public function create(array $data): FlightJustification
    {
        return FlightJustification::create($data);
    }

    /**
     * Update a FlightJustification model.
     *
     * @param \App\Models\FlightJustification $justification
     * @param array $data
     * @return \App\Models\FlightJustification
     */
    public function update(FlightJustification $justification, array $data): FlightJustification
    {
        $justification->update($data);
        return $justification;
    }

    /**
     * Delete a FlightJustification model.
     *
     * @param \App\Models\FlightJustification $justification
     */
    public function delete(FlightJustification $justification): void
    {
        $justification->delete();
    }
}
