<?php

namespace App\Imports;

use App\Models\Operator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class OperatorsImport implements
    ToCollection,
    WithHeadingRow,
    WithCustomCsvSettings,
    SkipsOnError,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    public int   $created  = 0;
    public int   $updated  = 0;
    public array $errors   = [];

    public function __construct(
        private readonly string $delimiter = ';',
        private readonly string $encoding  = 'UTF-8',
    ) {}

    // ── Laravel Excel config ──────────────────────────────────────

    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 500; }

    public function getCsvSettings(): array
    {
        return [
            'delimiter'        => $this->delimiter,
            'input_encoding'   => $this->encoding,
            'use_bom'          => false,
        ];
    }

    // ── Traitement ligne par ligne ────────────────────────────────

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2; // ligne 1 = en-têtes

            // --- Validation ---
            try {
                Validator::make($row->toArray(), [
                    'name'        => 'required|string|max:255',
                    'sigle'       => 'required|string|max:10',
                    'flight_type' => 'required|in:regular,non_regular',
                    'iata_code'   => 'nullable|string|min:2|max:5',
                    'icao_code'   => 'nullable|string|min:3|max:5',
                    'country'     => 'nullable|string|max:100',
                ], [
                    'name.required'        => 'La colonne "name" est obligatoire',
                    'sigle.required'       => 'La colonne "sigle" est obligatoire',
                    'flight_type.required' => 'La colonne "flight_type" est obligatoire',
                    'flight_type.in'       => '"flight_type" doit être regular ou non_regular',
                ])->validate();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $this->errors[] = ['row' => $line, 'message' => "[$field] {$messages[0]}"];
                }
                continue;
            }

            // --- Upsert sur sigle (clé métier unique) ---
            $sigle = strtoupper(trim($row['sigle']));

            $data = [
                'name'        => trim($row['name']),
                'flight_type' => trim($row['flight_type']),
                'iata_code'   => $this->clean($row['iata_code']  ?? ''),
                'icao_code'   => $this->clean($row['icao_code']  ?? ''),
                'country'     => $this->clean($row['country']    ?? ''),
            ];

            $exists = Operator::where('sigle', $sigle)->exists();

            Operator::updateOrCreate(['sigle' => $sigle], $data);

            $exists ? $this->updated++ : $this->created++;
        }
    }

    // ── Erreurs fatales / échecs de validation Maatwebsite ────────

    public function onError(Throwable $e): void
    {
        $this->errors[] = ['row' => 0, 'message' => $e->getMessage()];
    }

    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $f) {
            $this->errors[] = [
                'row'     => $f->row(),
                'message' => "[{$f->attribute()}] " . implode(', ', $f->errors()),
            ];
        }
    }

    // ── Helper ────────────────────────────────────────────────────

    private function clean(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }
}