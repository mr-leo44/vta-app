<?php

namespace App\Repositories;

use App\Models\MonthlyRate;
use Illuminate\Support\Collection;

interface MonthlyRateRepositoryInterface
{
    public function all(): Collection;
    public function create(array $data): MonthlyRate;
    public function update(MonthlyRate $monthlyRate, array $data): MonthlyRate;
    public function delete(MonthlyRate $monthlyRate): bool;
    public function findByMonth(string $month): ?MonthlyRate;
}
