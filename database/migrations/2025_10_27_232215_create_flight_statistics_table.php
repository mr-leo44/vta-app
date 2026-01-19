<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_statistics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('flight_id')->constrained('flights')->cascadeOnDelete();

            $table->integer('passengers_count')->default(0);
            $table->integer('pax_bus')->default(0);
            $table->integer('go_pass_count')->default(0);

            $table->json('fret_count')->nullable();    
            $table->json('excedents')->nullable();     

            $table->integer('passengers_ecart')->default(0);
            $table->boolean('has_justification')->default(false);
            $table->json('justification')->nullable(); 

            $table->timestamps();

            // indexes
            $table->index('flight_id');
            $table->index('has_justification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_statistics');
    }
};
