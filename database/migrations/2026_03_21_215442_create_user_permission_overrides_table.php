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
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('permission'); // valeur de l'enum Permission (ex: flight.validate)

            $table->enum('type', ['grant', 'revoke']);

            // Qui a accordé / révoqué cette permission
            $table->foreignId('granted_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('reason')->nullable(); // justification optionnelle (auditabilité)

            $table->timestamps();

            // Un seul override par permission par utilisateur
            $table->unique(['user_id', 'permission'], 'upo_user_permission_unique');
            $table->index(['user_id', 'type'], 'upo_user_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
