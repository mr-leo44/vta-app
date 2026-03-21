<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes d'audit et SoftDeletes sur les tables sensibles.
 *
 * Colonnes ajoutées :
 *  - created_by : FK vers users (qui a créé l'enregistrement)
 *  - updated_by : FK vers users (qui l'a modifié en dernier)
 *  - deleted_at : SoftDelete (l'enregistrement n'est pas physiquement supprimé)
 *
 * Pour les flights, ajoute en plus :
 *  - is_validated  : boolean
 *  - validated_by  : FK vers users
 *  - validated_at  : timestamp
 */
return new class extends Migration
{
    private array $tables = ['flights', 'aircrafts', 'aircraft_types', 'operators'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('created_by')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete()
                      ->after('id');

                $table->foreignId('updated_by')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete()
                      ->after('created_by');

                $table->softDeletes();
            });
        }

        // Colonnes de validation spécifiques aux vols
        Schema::table('flights', function (Blueprint $table) {
            $table->boolean('is_validated')
                  ->default(false)
                  ->after('status');

            $table->foreignId('validated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('is_validated');

            $table->timestamp('validated_at')
                  ->nullable()
                  ->after('validated_by');

            $table->index('is_validated');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['is_validated', 'validated_by', 'validated_at']);
        });

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropColumn(['created_by', 'updated_by']);
                $table->dropSoftDeletes();
            });
        }
    }
};
