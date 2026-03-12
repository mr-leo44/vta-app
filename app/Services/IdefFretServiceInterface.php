<?php

namespace App\Services;

use App\Models\IdefFret;
use Illuminate\Support\Collection;

interface IdefFretServiceInterface
{
    public function createIdefFret(array $data): IdefFret;
    public function updateIdefFret(IdefFret $idefFret, array $data): IdefFret;
    public function deleteIdefFret(IdefFret $idefFret): bool;
    public function findByDate(string $date): ?IdefFret;
    public function getByDateRange(string $from, string $to): Collection;
}
