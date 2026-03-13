<?php

namespace App\Repositories;

use App\Models\IdefFret;
use Illuminate\Support\Collection;

interface IdefFretRepositoryInterface
{
    public function create(array $data): IdefFret;
    public function update(IdefFret $idefFret, array $data): IdefFret;
    public function delete(IdefFret $idefFret): bool;
    public function findByDate(string $date): ?IdefFret;
    public function getByDateRange(string $from, string $to): Collection;
    public function upsertBatch(array $entries): array;
}
