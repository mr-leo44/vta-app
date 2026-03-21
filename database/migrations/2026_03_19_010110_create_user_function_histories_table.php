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
                  ->constrained()
                  ->cascadeOnDelete();

            // Valeurs issues de l'enum UserFunction
            $table->string('function')->default(UserFunction::VTA->value);

            $table->date('start_date');
            $table->date('end_date')->nullable();  // null = fonction active

            $table->timestamps();

            // Index pour accélérer la recherche de la fonction active
            $table->index(['user_id', 'end_date']);
            // Index pour les contraintes singleton (cherche par function + end_date)
            $table->index(['function', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_function_histories');
    }
};
