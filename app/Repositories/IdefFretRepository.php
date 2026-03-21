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
     */
    public function create(array $data): IdefFret
    {
        return IdefFret::create($data);
    }

    /**
     * Update an existing idef fret entry.
     */
    public function update(IdefFret $idefFret, array $data): IdefFret
    {
        $idefFret->update($data);
        return $idefFret->refresh();
    }

    /**
     * Delete an idef fret entry.
     */
    public function delete(IdefFret $idefFret): bool
    {
        return $idefFret->delete();
    }

    /**
     * Find an idef fret entry by date.
     */
    public function findByDate(string $date): ?IdefFret
    {
        return IdefFret::whereDate('date', Carbon::parse($date))->first();
    }

    /**
     * Get idef frets by date range.
     */
    public function getByDateRange(string $from, string $to): Collection
    {
        return IdefFret::whereBetween('date', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay()
        ])->orderBy('date')->get();
    }

    /**
     * Upsert a batch of idef fret entries.
     *
     * For each entry:
     *  - if a record with the same date exists → update usd & cdf
     *  - otherwise → create a new record
     *
     * Returns the lists of created and updated models.
     *
     * @param  array  $entries  e.g. [['date'=>'2026-02-01','usd'=>120,'cdf'=>0], ...]
     * @return array{created: IdefFret[], updated: IdefFret[]}
     */
    public function upsertBatch(array $entries): array
    {
        $created = [];
        $updated = [];

        // Pre-fetch all dates present in this batch to avoid N+1 queries
        $dates = array_map(
            fn($e) => Carbon::parse($e['date'])->toDateString(),
            $entries
        );

        $existing = IdefFret::whereIn('date', $dates)
            ->get()
            ->keyBy(fn($m) => Carbon::parse($m->date)->toDateString());

        foreach ($entries as $entry) {
            $dateKey = Carbon::parse($entry['date'])->toDateString();

            if ($existing->has($dateKey)) {
                $model = $existing->get($dateKey);
                $model->update([
                    'usd' => $entry['usd'],
                    'cdf' => $entry['cdf'],
                ]);
                $updated[] = $model->refresh();
            } else {
                $model = IdefFret::create([
                    'date' => $dateKey,
                    'usd'  => $entry['usd'],
                    'cdf'  => $entry['cdf'],
                ]);
                $created[] = $model;
            }
        }

        return compact('created', 'updated');
    }
}