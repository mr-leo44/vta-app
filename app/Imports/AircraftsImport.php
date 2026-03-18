<?php

namespace App\Imports;

use App\Models\Aircraft;
use App\Models\AircraftType;
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

class AircraftsImport implements
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

    // Cache sigle → id pour éviter N+1 queries
    private ?Collection $operatorMap = null;  // sigle → operator_id
    private ?Collection $typeMap     = null;  // sigle → aircraft_type_id

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
        // Charger les maps une seule fois (pas de requête SQL par ligne)
        $this->operatorMap ??= Operator::pluck('id', 'sigle')
            ->mapWithKeys(fn($id, $sigle) => [strtoupper($sigle) => $id]);

        $this->typeMap ??= AircraftType::pluck('id', 'sigle')
            ->mapWithKeys(fn($id, $sigle) => [strtoupper($sigle) => $id]);

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            // --- Validation structure du fichier ---
            try {
                Validator::make($row->toArray(), [
                    'immatriculation'     => 'required|string|max:20',
                    'operator_sigle'      => 'required|string|max:10',
                    'aircraft_type_sigle' => 'required|string|max:10',
                    'pmad'                => 'nullable|numeric|min:0',
                    'in_activity'         => 'nullable|in:0,1',
                ], [
                    'immatriculation.required'     => 'La colonne "immatriculation" est obligatoire',
                    'operator_sigle.required'      => 'La colonne "operator_sigle" est obligatoire',
                    'aircraft_type_sigle.required' => 'La colonne "aircraft_type_sigle" est obligatoire',
                    'pmad.numeric'                 => '"pmad" doit être un nombre',
                    'in_activity.in'               => '"in_activity" doit être 0 ou 1',
                ])->validate();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $field => $messages) {
                    $this->errors[] = ['row' => $line, 'message' => "[$field] {$messages[0]}"];
                }
                continue;
            }

            $immat         = strtoupper(trim($row['immatriculation']));
            $operatorSigle = strtoupper(trim($row['operator_sigle']));
            $typeSigle     = strtoupper(trim($row['aircraft_type_sigle']));

            // --- Résolution FK via cache ---
            $operatorId = $this->operatorMap->get($operatorSigle);
            if (! $operatorId) {
                $this->errors[] = [
                    'row'     => $line,
                    'message' => "Exploitant introuvable avec le sigle \"$operatorSigle\" — importez d'abord les exploitants",
                ];
                continue;
            }

            $typeId = $this->typeMap->get($typeSigle);
            if (! $typeId) {
                $this->errors[] = [
                    'row'     => $line,
                    'message' => "Type d'aéronef introuvable avec le sigle \"$typeSigle\" — importez d'abord les types",
                ];
                continue;
            }

            // --- Préparation des données ---
            // pmad : null si absent ou vide (colonne optionnelle)
            $pmad = (isset($row['pmad']) && $row['pmad'] !== '') ? (int) $row['pmad'] : null;

            // in_activity : true par défaut si absent
            $inActivity = (isset($row['in_activity']) && $row['in_activity'] !== '')
                ? (bool)(int) $row['in_activity']
                : true;

            $data = [
                'operator_id'      => $operatorId,
                'aircraft_type_id' => $typeId,  // <-- colonne exacte confirmée par l'API
                'in_activity'      => $inActivity,
            ];

            if ($pmad !== null) {
                $data['pmad'] = $pmad;
            } else {
                $data['pmad'] = AircraftType::find($typeId)->default_pmad ?? 0;
            }

            // --- Upsert sur immatriculation ---
            $exists = Aircraft::where('immatriculation', $immat)->exists();

            Aircraft::updateOrCreate(['immatriculation' => $immat], $data);

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