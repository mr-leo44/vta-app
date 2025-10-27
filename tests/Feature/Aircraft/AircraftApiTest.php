<?php

namespace Tests\Feature\Aircraft;

use Tests\TestCase;
use App\Models\Aircraft;
use App\Models\Operator;
use App\Models\AircraftType;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function PHPUnit\Framework\assertJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->baseUrl = '/api/aircrafts';
});

it('can list all aircrafts', function () {
    Aircraft::factory()->count(3)->create();

    $response = $this->getJson($this->baseUrl);

    $response->assertOk()
        ->assertJsonStructure(['*' => ['id', 'immatriculation', 'pmad', 'in_activity', 'aircraft_type_id', 'operator_id']]);
});

it('can create a new aircraft', function () {
    $operator = Operator::factory()->create();
    $type = AircraftType::factory()->create();

    $data = [
        'immatriculation' => '9Q-ABC',
        'pmad' => 20000,
        'in_activity' => true,
        'aircraft_type_id' => $type->id,
        'operator_id' => $operator->id
    ];

    $response = $this->postJson($this->baseUrl, $data);

    $response->assertCreated()->assertJsonFragment(['immatriculation' => '9Q-ABC']);
    $this->assertDatabaseHas('aircrafts', ['immatriculation' => '9Q-ABC']);
});

// it('can show a specific aircraft', function () {
//     $aircraft = Aircraft::factory()->create();

//     $response = $this->getJson("{$this->baseUrl}/{$aircraft->id}");

//     $response->assertOk()->assertJsonFragment(['immatriculation' => $aircraft->immatriculation]);
// });

it('can update an aircraft', function () {
    $aircraft = Aircraft::factory()->create();
    $updateData = ['immatriculation' => '9Q-XYZ', 'in_activity' => false, 'aircraft_type_id' => AircraftType::factory()->create()->id, 'pmad' => 30000, 'operator_id' => Operator::factory()->create()->id];

    $response = $this->putJson("{$this->baseUrl}/{$aircraft->id}", $updateData);

    $response->assertOk()->assertJsonFragment(['in_activity' => false]);
    $this->assertDatabaseHas('aircrafts', ['id' => $aircraft->id, 'in_activity' => false]);
});

it('can delete an aircraft', function () {
    $aircraft = Aircraft::factory()->create();

    $response = $this->deleteJson("{$this->baseUrl}/{$aircraft->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('aircrafts', ['id' => $aircraft->id]);
});

it('can search aircraft by immatriculation', function () {
    Aircraft::factory()->create(['immatriculation' => '9Q-ABC']);
    Aircraft::factory()->create(['immatriculation' => '5X-DEF']);

    $response = $this->getJson("{$this->baseUrl}/search?term=9Q-ABC");

    $response->assertOk()->assertJsonFragment(['immatriculation' => '9Q-ABC'])->assertJsonMissing(['immatriculation' => '5X-DEF']);
});

it('can list aircrafts by operator', function () {
    $operator = Operator::factory()->create();
    $aircraftType = AircraftType::factory()->create();
    $aircraft = Aircraft::factory()->create([
        'operator_id' => $operator->id,
        'immatriculation' => '9S-381',
        'pmad' => 74914,
        'in_activity' => 1,
        'aircraft_type_id' => $aircraftType->id,
    ]);

    $response = $this->getJson("/api/operators/{$operator->id}/aircrafts");

    $response->assertOk();
    $response->assertJsonStructure([
        '*' => [
            'id',
            'immatriculation',
            'pmad',
            'in_activity',
            'aircraft_type_id',
            'operator_id',
            'created_at',
            'updated_at',
        ],
    ]);
});
