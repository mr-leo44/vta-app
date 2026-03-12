<?php

namespace App\Services;

use App\Models\MonthlyRate;
use App\Repositories\MonthlyRateRepositoryInterface;
use App\Services\MonthlyRateServiceInterface;
use Illuminate\Support\Collection;

class MonthlyRateService implements MonthlyRateServiceInterface
{
    protected $monthlyRateRepository;

    public function __construct(MonthlyRateRepositoryInterface $monthlyRateRepository)
    {
        $this->monthlyRateRepository = $monthlyRateRepository;
    }

    public function getAllMonthlyRates(): Collection
    {
        return $this->monthlyRateRepository->all();
    }


    public function createMonthlyRate(array $data): MonthlyRate
    {
        return $this->monthlyRateRepository->create($data);
    }

    public function updateMonthlyRate(MonthlyRate $monthlyRate, array $data): MonthlyRate
    {
        return $this->monthlyRateRepository->update($monthlyRate, $data);
    }

    public function deleteMonthlyRate(MonthlyRate $monthlyRate): bool
    {
        return $this->monthlyRateRepository->delete($monthlyRate);
    }

    public function findByMonth(string $month, string $year): ?MonthlyRate
    {
        return $this->monthlyRateRepository->findByMonth($month, $year);
    }
}
