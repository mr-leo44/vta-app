<?php

use App\Enums\FlightTypeEnum;
use App\Enums\FlightNatureEnum;
use App\Enums\FlightRegimeEnum;
use App\Enums\FlightStatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();

            // unique flight number
            $table->string('flight_number')->unique();

            // relations
            $table->foreignId('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignId('aircraft_id')->constrained('aircrafts')->cascadeOnDelete();

            // enums stored as strings with defaults
            // Enums
            $table->string('flight_regime')->default(FlightRegimeEnum::DOMESTIC->value);
            $table->string('flight_type')->default(FlightTypeEnum::REGULAR->value);
            $table->string('flight_nature')->default(FlightNatureEnum::COMMERCIAL->value);
            $table->string('status')->default(FlightStatusEnum::SCHEDULED->value);   // FlightStatusEnum default: scheduled

            // departure & arrival as JSON (code and name)
            $table->json('departure'); // example: {"iata":"FIH","name":"N'Djili"}
            $table->json('arrival');   // example: {"iata":"LUB","name":"Lubumbashi"}

            // times
            $table->dateTime('departure_time');
            $table->dateTime('arrival_time');

            // optional remarks
            $table->text('remarks')->nullable();

            $table->timestamps();

            // indexes
            $table->index(['operator_id']);
            $table->index(['aircraft_id']);
            $table->index(['departure_time']);
            $table->index(['arrival_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
