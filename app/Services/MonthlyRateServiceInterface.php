<?php

namespace App\Services;

use App\Models\MonthlyRate;
use Illuminate\Support\Collection;

interface MonthlyRateServiceInterface
{
    public function getAllMonthlyRates(): Collection;
    public function createMonthlyRate(array $data): MonthlyRate;
    public function updateMonthlyRate(MonthlyRate $monthlyRate, array $data): MonthlyRate;
    public function deleteMonthlyRate(MonthlyRate $monthlyRate): bool;
    public function findByMonth(string $month, string $year): ?MonthlyRate;
}
