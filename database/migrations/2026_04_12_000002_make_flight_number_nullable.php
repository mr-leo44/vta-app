<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make flight_number nullable in flights table.
 * Raison : pas tous les vols ont un numéro de vol officiel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('flight_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table) {
            $table->string('flight_number')->nullable(false)->change();
        });
    }
};
