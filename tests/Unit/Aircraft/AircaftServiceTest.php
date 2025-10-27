<?php

namespace Tests\Unit\Aircraft;

use Mockery;
use Tests\TestCase;
use App\Models\Aircraft;
use App\Services\AircraftService;
use App\Repositories\AircraftRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->repo = Mockery::mock(AircraftRepositoryInterface::class);
    $this->service = new AircraftService($this->repo);
});

afterEach(function () {
    Mockery::close();
});

it('can return all aircrafts', function () {
    $aircrafts = new Collection([new Aircraft(['immatriculation' => '9Q-ABC'])]);
    $this->repo->shouldReceive('all')->once()->andReturn($aircrafts);

    $result = $this->service->getAll();

    expect($result)->toBe($aircrafts);
});

it('can create a new aircraft', function () {
    $data = [
        'immatriculation' => '9Q-XYZ',
        'pmad' => 50000,
        'in_activity' => true,
        'aircraft_type_id' => 1,
        'operator_id' => 1,
    ];

    $aircraft = new Aircraft($data);
    $this->repo->shouldReceive('create')->with($data)->once()->andReturn($aircraft);

    $result = $this->service->store($data);

    expect($result->immatriculation)->toBe('9Q-XYZ');
    expect($result->pmad)->toBe(50000);
});

it('can update an aircraft', function () {
    $aircraft = new Aircraft(['immatriculation' => '9Q-OLD']);
    $updateData = ['immatriculation' => '9Q-NEW'];

    $this->repo->shouldReceive('update')->with($aircraft, $updateData)->once()->andReturn(new Aircraft($updateData));

    $result = $this->service->update($aircraft, $updateData);

    expect($result->immatriculation)->toBe('9Q-NEW');
});


// it('can delete an aircraft', function () {
//     $aircraft = new Aircraft(['immatriculation' => '9Q-ABC']);
//     $this->repo->shouldReceive('delete')->with($aircraft)->once()->andReturn(true);

//     expect($this->service->delete($aircraft))->toBeTrue();
// });

it('can search aircraft by immatriculation', function () {
    $aircraft = new Aircraft(['immatriculation' => '9Q-XYZ']);
    $this->repo->shouldReceive('findByImmatriculation')->with('9Q-XYZ')->once()->andReturn($aircraft);

    $result = $this->service->findByImmatriculation('9Q-XYZ');

    expect($result->immatriculation)->toBe('9Q-XYZ');
});

it('can get aircrafts by operator', function () {
    $aircrafts = new Collection([new Aircraft(['immatriculation' => '9Q-AAA'])]);
    $this->repo->shouldReceive('findByOperator')->with(1)->once()->andReturn($aircrafts);

    $result = $this->service->findByOperator(1);

    expect($result)->toBe($aircrafts);
});
