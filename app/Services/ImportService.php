<?php

namespace App\Services;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\Operator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

class ImportService
{
    private array $created = [];
    private array $updated = [];
    private array $errors  = [];

    // ─────────────────────────────────────────────────────────────
    // Point d'entrée public
    // ─────────────────────────────────────────────────────────────

    public function import(UploadedFile $file, string $type, array $options): array
    {
        $this->created = [];
        $this->updated = [];
        $this->errors  = [];

        $rows = $this->parseFile($file, $options);

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Les index commencent à 1 ; si has_header, ligne 1 = headers
                $lineNumber = $index + ($options['has_header'] ? 2 : 1);

                match ($type) {
                    'operators'      => $this->processOperator($row, $lineNumber),
                    'aircrafts'      => $this->processAircraft($row, $lineNumber),
                    'aircraft-types' => $this->processAircraftType($row, $lineNumber),
                };
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImportService fatal error', ['message' => $e->getMessage()]);
            throw $e;
        }

        return [
            'created' => count($this->created),
            'updated' => count($this->updated),
            'failed'  => count($this->errors),
            'errors'  => $this->errors,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Parsing fichier → tableau de lignes associatives
    // ─────────────────────────────────────────────────────────────

    private function parseFile(UploadedFile $file, array $options): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->parseCsv($file, $options);
        }

        return $this->parseExcel($file, $options);
    }

    private function parseCsv(UploadedFile $file, array $options): array
    {
        $delimiter  = $options['delimiter'] ?? ';';
        $encoding   = $options['encoding']  ?? 'UTF-8';
        $hasHeader  = $options['has_header'] ?? true;

        $content = file_get_contents($file->getPathname());

        // Convertir en UTF-8 si nécessaire
        if (strtoupper($encoding) !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Supprimer le BOM UTF-8 si présent
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_filter(
            explode("\n", str_replace("\r\n", "\n", $content)),
            fn($l) => trim($l) !== ''
        );
        $lines = array_values($lines);

        if (empty($lines)) {
            return [];
        }

        // Si la tabulation est le délimiteur, gérer l'escape \t
        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        if ($hasHeader) {
            $headers = array_map('trim', str_getcsv(array_shift($lines), $delimiter));
            return array_map(
                fn($line) => array_combine(
                    $headers,
                    array_pad(array_map('trim', str_getcsv($line, $delimiter)), count($headers), '')
                ),
                $lines
            );
        }

        return array_map(
            fn($line) => array_map('trim', str_getcsv($line, $delimiter)),
            $lines
        );
    }

    private function parseExcel(UploadedFile $file, array $options): array
    {
        $hasHeader = $options['has_header'] ?? true;
        $sheetName = $options['sheet'] ?? '__first__';

        $spreadsheet = IOFactory::load($file->getPathname());

        $sheet = ($sheetName === '__first__' || empty($sheetName))
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();

        $data = $sheet->toArray(null, true, true, false);
        $data = array_filter($data, fn($row) => array_filter($row, fn($v) => $v !== null && $v !== ''));
        $data = array_values($data);

        if (empty($data)) {
            return [];
        }

        if ($hasHeader) {
            $headers = array_map(fn($v) => trim((string) $v), array_shift($data));
            return array_map(
                fn($row) => array_combine(
                    $headers,
                    array_pad(array_map(fn($v) => trim((string) $v), $row), count($headers), '')
                ),
                $data
            );
        }

        return array_map(
            fn($row) => array_map(fn($v) => trim((string) $v), $row),
            $data
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Traitement des lignes par type
    // ─────────────────────────────────────────────────────────────

    private function processOperator(array $row, int $line): void
    {
        $name       = trim($row['name'] ?? '');
        $sigle      = trim(strtoupper($row['sigle'] ?? ''));
        $flightType = trim($row['flight_type'] ?? '');

        // Validation
        if (empty($name)) {
            $this->addError($line, 'Colonne "name" manquante ou vide');
            return;
        }
        if (empty($sigle)) {
            $this->addError($line, 'Colonne "sigle" manquante ou vide');
            return;
        }
        if (!in_array($flightType, ['regular', 'non_regular'])) {
            $this->addError($line, "Colonne \"flight_type\" invalide (valeurs: regular, non_regular) — reçu: \"{$flightType}\"");
            return;
        }

        $data = [
            'name'        => $name,
            'flight_type' => $flightType,
            'iata_code'   => $this->nullIfEmpty($row['iata_code'] ?? ''),
            'icao_code'   => $this->nullIfEmpty($row['icao_code'] ?? ''),
            'country'     => $this->nullIfEmpty($row['country'] ?? ''),
        ];

        $operator = Operator::where('sigle', $sigle)->first();

        if ($operator) {
            $operator->update($data);
            $this->updated[] = $sigle;
        } else {
            Operator::create(array_merge($data, ['sigle' => $sigle]));
            $this->created[] = $sigle;
        }
    }

    private function processAircraft(array $row, int $line): void
    {
        $immat          = trim(strtoupper($row['immatriculation'] ?? ''));
        $operatorSigle  = trim(strtoupper($row['operator_sigle'] ?? ''));
        $typeSigle      = trim(strtoupper($row['aircraft_type_sigle'] ?? ''));

        if (empty($immat)) {
            $this->addError($line, 'Colonne "immatriculation" manquante ou vide');
            return;
        }
        if (empty($operatorSigle)) {
            $this->addError($line, 'Colonne "operator_sigle" manquante ou vide');
            return;
        }
        if (empty($typeSigle)) {
            $this->addError($line, 'Colonne "aircraft_type_sigle" manquante ou vide');
            return;
        }

        // Résolution des clés étrangères
        $operator = Operator::where('sigle', $operatorSigle)->first();
        if (!$operator) {
            $this->addError($line, "Exploitant introuvable avec le sigle \"{$operatorSigle}\"");
            return;
        }

        $type = AircraftType::where('sigle', $typeSigle)->first();
        if (!$type) {
            $this->addError($line, "Type d'aéronef introuvable avec le sigle \"{$typeSigle}\"");
            return;
        }

        $pmad       = isset($row['pmad']) && $row['pmad'] !== '' ? (int) $row['pmad'] : null;
        $inActivity = isset($row['in_activity']) ? (bool)(int)$row['in_activity'] : true;

        $data = [
            'operator_id'      => $operator->id,
            'aircraft_type_id' => $type->id,
            'in_activity'      => $inActivity,
        ];

        if ($pmad !== null) {
            $data['pmad'] = $pmad;
        }

        $aircraft = Aircraft::where('immatriculation', $immat)->first();

        if ($aircraft) {
            $aircraft->update($data);
            $this->updated[] = $immat;
        } else {
            Aircraft::create(array_merge($data, ['immatriculation' => $immat]));
            $this->created[] = $immat;
        }
    }

    private function processAircraftType(array $row, int $line): void
    {
        $sigle       = trim(strtoupper($row['sigle'] ?? ''));
        $name        = trim($row['name'] ?? '');
        $defaultPmad = $row['default_pmad'] ?? '';

        if (empty($sigle)) {
            $this->addError($line, 'Colonne "sigle" manquante ou vide');
            return;
        }
        if (empty($name)) {
            $this->addError($line, 'Colonne "name" manquante ou vide');
            return;
        }
        if ($defaultPmad === '' || !is_numeric($defaultPmad)) {
            $this->addError($line, "Colonne \"default_pmad\" manquante ou non numérique — reçu: \"{$defaultPmad}\"");
            return;
        }

        $data = [
            'name'         => $name,
            'default_pmad' => (int) $defaultPmad,
        ];

        $type = AircraftType::where('sigle', $sigle)->first();

        if ($type) {
            $type->update($data);
            $this->updated[] = $sigle;
        } else {
            AircraftType::create(array_merge($data, ['sigle' => $sigle]));
            $this->created[] = $sigle;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function addError(int $line, string $message): void
    {
        $this->errors[] = ['row' => $line, 'message' => $message];
    }

    private function nullIfEmpty(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}