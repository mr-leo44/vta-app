<?php

namespace App\Imports;

use App\Models\AircraftType;
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

class AircraftTypesImport implements
    ToCollection,
    WithHeadingRow,
    WithCustomCsvSettings,
    SkipsOnError,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    public int   $created = 0;
    public int   $updated = 0;
    public array $errors  = [];

    public function __construct(
        private readonly string $delimiter = ';',
        private readonly string $encoding  = 'UTF-8',
    ) {}

    public function batchSize(): int { return 200; }
    public function chunkSize(): int { return 500; }

    public function getCsvSettings(): array
    {
        return [
            'delimiter'      => $this->delimiter,
            'input_encoding' => $this->encoding,
            'use_bom'        => false,
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                Validator::make($row->toArray(), [
                    'sigle'        => 'required|string|max:10',
                    'name'         => 'required|string|max:255',
                    'default_pmad' => 'required|numeric|min:0',
                ], [
                    'sigle.required'        => 'La colonne "sigle" est obligatoire',
                    'name.required'         => 'La colonne "name" est obligatoire',
                    'default_pmad.required' => 'La colonne "default_pmad" est obligatoire',
                    'default_pmad.numeric'  => '"default_pmad" doit être un nombre',
                ])->validate();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $this->errors[] = ['row' => $line, 'message' => "[$field] {$messages[0]}"];
                }
                continue;
            }

            $sigle = strtoupper(trim($row['sigle']));

            $data = [
                'name'         => trim($row['name']),
                'default_pmad' => (int) $row['default_pmad'],
            ];

            $exists = AircraftType::where('sigle', $sigle)->exists();

            AircraftType::updateOrCreate(['sigle' => $sigle], $data);

            $exists ? $this->updated++ : $this->created++;
        }
    }

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
}