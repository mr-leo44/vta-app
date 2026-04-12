<?php

namespace App\Jobs;

use App\Imports\AircraftsImport;
use App\Imports\AircraftTypesImport;
use App\Imports\OperatorsImport;
use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $importType;
    protected string $filePath;
    protected string $delimiter;
    protected string $encoding;

    /**
     * Create a new job instance.
     */
    public function __construct(string $importType, string $filePath, string $delimiter = ';', string $encoding = 'UTF-8')
    {
        $this->importType = $importType;
        $this->filePath   = $filePath;
        $this->delimiter  = $delimiter;
        $this->encoding   = $encoding;
    }

    /**
     * Execute the job.
     * Désactive les observers pendant l'import pour éviter des milliers de logs
     * qui bouffent la mémoire. Crée 1 log résumé à la fin.
     */
    public function handle(): void
    {
        // Instancier l'import approprié
        $import = match ($this->importType) {
            'operators'      => new OperatorsImport($this->delimiter, $this->encoding),
            'aircrafts'      => new AircraftsImport($this->delimiter, $this->encoding),
            'aircraft-types' => new AircraftTypesImport($this->delimiter, $this->encoding),
            default          => throw new \InvalidArgumentException("Unknown import type: {$this->importType}"),
        };

        // Limiter le nombre de lignes pour éviter les débordements mémoire
        ini_set('memory_limit', '512M');

        try {
            // Désactiver les observers pendant l'import
            // pour éviter des milliers de logs individuels
            Model::withoutEvents(function () use ($import) {
                Excel::import($import, $this->filePath);
            });

            // Créer 1 log RÉSUMÉ à la fin
            AuditLog::create([
                'event'          => 'import_completed',
                'auditable_type' => 'App\\Imports\\' . ucfirst($this->importType),
                'auditable_id'   => 0, // pas d'ID spécifique
                'actor_id'       => null, // job lancé sans user
                'actor_ip'       => null,
                'actor_agent'    => null,
                'old_values'     => null,
                'new_values'     => [
                    'import_type' => $this->importType,
                    'created'     => $import->created,
                    'updated'     => $import->updated,
                    'failed'      => count($import->errors),
                    'errors'      => $import->errors,
                ],
            ]);

            Log::info("Import {$this->importType} completed", [
                'created' => $import->created,
                'updated' => $import->updated,
                'failed'  => count($import->errors),
            ]);
        } catch (\Exception $e) {
            // Log l'erreur d'import
            AuditLog::create([
                'event'          => 'import_failed',
                'auditable_type' => 'App\\Imports\\' . ucfirst($this->importType),
                'auditable_id'   => 0,
                'actor_id'       => null,
                'actor_ip'       => null,
                'actor_agent'    => null,
                'old_values'     => null,
                'new_values'     => [
                    'import_type' => $this->importType,
                    'error'       => $e->getMessage(),
                ],
            ]);

            Log::error("Import {$this->importType} failed: " . $e->getMessage());
            throw $e;
        } finally {
            // Nettoyer le fichier temporaire
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        }
    }
}
