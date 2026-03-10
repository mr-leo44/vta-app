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
        Schema::create('monthly_rates', function (Blueprint $table) {
            $table->id();
            $table->string('month'); // Format: YYYY-MM
            $table->integer('rate')->default(2000);
            $table->timestamps();

            $table->unique('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_rates');
    }
};
