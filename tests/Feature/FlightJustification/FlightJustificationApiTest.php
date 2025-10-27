<?php

use App\Models\FlightJustification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->baseUrl = '/api/flight-justifications';
});

it('can list all flight justifications', function () {
    FlightJustification::factory()->create(['name' => 'Humanitarian']);
    FlightJustification::factory()->create(['name' => 'Medical']);

    $response = $this->getJson($this->baseUrl);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Humanitarian'])
        ->assertJsonFragment(['name' => 'Medical']);
});

it('can create a new flight justification', function () {
    $data = ['name' => 'Rescue mission'];

    $response = $this->postJson($this->baseUrl, $data);

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'Rescue mission']);

    $this->assertDatabaseHas('flight_justifications', $data);
});

it('can show a specific flight justification', function () {
    $justification = FlightJustification::factory()->create(['name' => 'Cargo flight']);

    $response = $this->getJson("{$this->baseUrl}/{$justification->id}");

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Cargo flight']);
});

it('can update a flight justification', function () {
    $justification = FlightJustification::factory()->create(['name' => 'Old name']);
    $data = ['name' => 'Updated name'];

    $response = $this->putJson("{$this->baseUrl}/{$justification->id}", $data);

    $response->assertOk()
        ->assertJsonFragment(['name' => 'Updated name']);

    $this->assertDatabaseHas('flight_justifications', $data);
});

it('can delete a flight justification', function () {
    $justification = FlightJustification::factory()->create(['name' => 'Delete me']);

    $response = $this->deleteJson("{$this->baseUrl}/{$justification->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('flight_justifications', ['id' => $justification->id]);
});
