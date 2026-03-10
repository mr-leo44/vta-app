<?php

namespace App\Repositories;

use App\Models\MonthlyRate;
use App\Repositories\MonthlyRateRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyRateRepository implements MonthlyRateRepositoryInterface
{
    public function all(): Collection
    {
        return MonthlyRate::latest()->get();
    }

    public function create(array $data): MonthlyRate
    {
        return MonthlyRate::create($data);
    }

    public function update(MonthlyRate $monthlyRate, array $data): MonthlyRate
    {
        $monthlyRate->update($data);
        return $monthlyRate->refresh();
    }

    public function delete(MonthlyRate $monthlyRate): bool
    {
        return $monthlyRate->delete();
    }

    public function findByMonth(string $month): ?MonthlyRate
    {
        return MonthlyRate::where('month', $month)->first();
    }
}
