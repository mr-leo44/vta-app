<?php

namespace App\Services;

use App\Models\IdefFret;
use App\Repositories\IdefFretRepositoryInterface;
use Illuminate\Support\Collection;

class IdefFretService implements IdefFretServiceInterface
{
    protected $ideFretRepository;

    public function __construct(IdefFretRepositoryInterface $ideFretRepository)
    {
        $this->ideFretRepository = $ideFretRepository;
    }

    public function createIdefFret(array $data): IdefFret
    {
        return $this->ideFretRepository->create($data);
    }

    public function updateIdefFret(IdefFret $idefFret, array $data): IdefFret
    {
        return $this->ideFretRepository->update($idefFret, $data);
    }

    public function deleteIdefFret(IdefFret $idefFret): bool
    {
        return $this->ideFretRepository->delete($idefFret);
    }

    public function findByDate(string $date): ?IdefFret
    {
        return $this->ideFretRepository->findByDate($date);
    }

    public function getByDateRange(string $from, string $to): Collection
    {
        return $this->ideFretRepository->getByDateRange($from, $to);
    }
}
