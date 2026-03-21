<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {

            $table->id();

            $table->string('event', 20); // created | updated | deleted | restored | permission_granted | permission_revoked | function_assigned

            // Relation polymorphique vers le modèle audité
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            // Acteur (null si action système / console)
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_ip', 45)->nullable();   // IPv4 ou IPv6
            $table->string('actor_agent')->nullable();     // User-Agent tronqué

            // Diff avant/après (null pour created/deleted)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Un log a uniquement created_at — il ne se modifie jamais
            $table->timestamp('created_at')->useCurrent();

            // Index pour les requêtes fréquentes de la page audit
            $table->index(['auditable_type', 'auditable_id'], 'audit_morphable_idx');
            $table->index(['actor_id', 'created_at'],         'audit_actor_date_idx');
            $table->index(['event', 'created_at'],             'audit_event_date_idx');
            $table->index('created_at',                        'audit_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
