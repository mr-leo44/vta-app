<?php

namespace Tests\Feature\Operator;

use Tests\TestCase;
use App\Models\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->baseUrl = '/api/operators';
});

it('can list all operators', function () {
    Operator::factory()->count(3)->create();

    $response = $this->getJson($this->baseUrl);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'iata_code', 'icao_code', 'flight_regime', 'flight_type', 'flight_nature']
            ]
        ]);
});

it('can create a new operator', function () {
    $data = [
        'name' => 'Congo Airways',
        'iata_code' => '8Z',
        'icao_code' => 'CGA',
        'flight_regime' => 'domestic',   // Enum: 'domestic' | 'international'
        'flight_type' => 'regular',      // Enum: 'regular' | 'non-regular'
        'flight_nature' => 'commercial', // Enum: 'commercial' | 'non-commercial'
        'country' => 'RDC',              // Exemple de champ additionnel
    ];

    $response = $this->postJson($this->baseUrl, $data);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'Congo Airways']);

    $this->assertDatabaseHas('operators', ['name' => 'Congo Airways']);
});

it('can show a specific operator', function () {
    $operator = Operator::factory()->create();

    $response = $this->getJson("{$this->baseUrl}/{$operator->id}");

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $operator->id,
            'name' => $operator->name
        ]);
});

it('can update an operator', function () {
    $operator = Operator::factory()->create();

    $updateData = ['name' => 'Updated Airways'];

    $response = $this->putJson("{$this->baseUrl}/{$operator->id}", $updateData);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Updated Airways']);

    $this->assertDatabaseHas('operators', ['name' => 'Updated Airways']);
});

it('can delete an operator', function () {
    $operator = Operator::factory()->create();

    $response = $this->deleteJson("{$this->baseUrl}/{$operator->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('operators', ['id' => $operator->id]);
});

it('can search operators by name and iata code', function () {
    // Création des opérateurs
    Operator::factory()->create([
        'name' => 'Congo Airways',
        'iata_code' => '8Z',
        'icao_code' => 'CGA',
        'flight_regime' => 'domestic',
        'flight_type' => 'regular',
        'flight_nature' => 'commercial',
        'country' => 'RDC',
    ]);

    Operator::factory()->create([
        'name' => 'Ethiopian Airlines',
        'iata_code' => 'ET',
        'icao_code' => 'ETH',
        'flight_regime' => 'international',
        'flight_type' => 'regular',
        'flight_nature' => 'commercial',
        'country' => 'Ethiopia',
    ]);

    $baseUrl = '/api/operators';

    // Recherche par nom
    $responseByName = $this->getJson("{$baseUrl}/search?term=Congo");
    $responseByName->assertOk()
        ->assertJsonFragment(['name' => 'Congo Airways'])
        ->assertJsonMissing(['name' => 'Ethiopian Airlines']);

    // Recherche par IATA code
    $responseByIata = $this->getJson("{$baseUrl}/search?term=ET");
    $responseByIata->assertOk()
        ->assertJsonFragment(['iata_code' => 'ET'])
        ->assertJsonMissing(['iata_code' => '8Z']);
});

