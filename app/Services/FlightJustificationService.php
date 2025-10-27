<?php

namespace App\Services;

use App\Models\FlightJustification;
use App\Repositories\FlightJustificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class FlightJustificationService
{
    public function __construct(
        protected FlightJustificationRepositoryInterface $repository
    ) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function create(array $data): FlightJustification
    {
        return $this->repository->create($data);
    }

    public function update(FlightJustification $justification, array $data): FlightJustification
    {
        return $this->repository->update($justification, $data);
    }

    public function delete(FlightJustification $justification): void
    {
        $this->repository->delete($justification);
    }
}
