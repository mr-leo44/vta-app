<?php

namespace App\Repositories;

use Illuminate\Support\Carbon;
use App\Models\IdefFret;
use Illuminate\Support\Collection;
use App\Repositories\IdefFretRepositoryInterface;

class IdefFretRepository implements IdefFretRepositoryInterface
{
    /**
     * Create a new idef fret entry.
     *
     * @param  array  $data
     * @return IdefFret
     */
    public function create(array $data): IdefFret
    {
        return IdefFret::create($data);
    }

    /**
     * Update an existing idef fret entry.
     *
     * @param  IdefFret  $idefFret
     * @param  array  $data
     * @return IdefFret
     */
    public function update(IdefFret $idefFret, array $data): IdefFret
    {
        $idefFret->update($data);
        return $idefFret->refresh();
    }


    /**
     * Delete an idef fret entry.
     *
     * @param  IdefFret  $idefFret
     * @return bool
     */
    public function delete(IdefFret $idefFret): bool
    {
        return $idefFret->delete();
    }



    /**
     * Find an idef fret entry by date.
     *
     * @param  string  $date  Date in 'YYYY-MM-DD' format
     * @return IdefFret|null  Idef fret entry if found, null otherwise
     */
    public function findByDate(string $date): ?IdefFret
    {
        return IdefFret::whereDate('date', Carbon::parse($date))->first();
    }



    /**
     * Get idef frets by date range.
     *
     * @param  string  $from  Start date in 'YYYY-MM-DD' format
     * @param  string  $to    End date in 'YYYY-MM-DD' format
     * @return Collection  Collection of idef fret entries
     */
    public function getByDateRange(string $from, string $to): Collection
    {
        return IdefFret::whereBetween('date', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay()
        ])->orderBy('date')->get();
    }
}
