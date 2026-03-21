<?php

use App\Enums\UserFunction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_function_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')   // explicite — évite toute ambiguïté
                  ->cascadeOnDelete();

            $table->string('function');    // pas de default — la valeur est toujours fournie

            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = fonction active

            $table->timestamps();

            // Recherche rapide de la fonction active d'un utilisateur
            $table->index(['user_id', 'end_date'], 'ufh_user_active_idx');

            // Recherche rapide des singletons actifs (contrainte unicité métier)
            $table->index(['function', 'end_date'], 'ufh_function_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_function_histories');
    }
};
