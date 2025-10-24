<?php

use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iata_code')->nullable();
            $table->string('icao_code')->nullable();
            $table->string('country')->nullable();

            // Enums
            $table->string('flight_regime')->default(FlightRegimeEnum::DOMESTIC->value);
            $table->string('flight_type')->default(FlightTypeEnum::REGULAR->value);
            $table->string('flight_nature')->default(FlightNatureEnum::COMMERCIAL->value);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
