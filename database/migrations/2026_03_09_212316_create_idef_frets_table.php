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
        Schema::create('idef_frets', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('usd')->default(0);
            $table->integer('cdf')->default(0);
            $table->timestamps();

            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idef_frets');
    }
};
