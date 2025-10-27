<?php

use App\Models\FlightJustification;
use App\Repositories\FlightJustificationRepositoryInterface;
use App\Services\FlightJustificationService;
use Illuminate\Support\Collection;
use Mockery;

beforeEach(function () {
    $this->repo = Mockery::mock(FlightJustificationRepositoryInterface::class);
    $this->service = new FlightJustificationService($this->repo);
});

afterEach(function () {
    Mockery::close();
});

it('can return all flight justifications', function () {
    $collection = new Collection([new FlightJustification(['name' => 'Evacuation'])]);
    $this->repo->shouldReceive('all')->once()->andReturn($collection);

    $result = $this->service->getAll();

    expect($result)->toBe($collection);

});

it('can create a new flight justification', function () {
    $data = ['name' => 'Training flight'];
    $justification = new FlightJustification($data);

    $this->repo->shouldReceive('create')->with($data)->once()->andReturn($justification);

    $result = $this->service->create($data);

    expect($result->name)->toBe('Training flight');
});

it('can update a flight justification', function () {
    $justification = new FlightJustification(['name' => 'Old name']);
    $data = ['name' => 'Updated name'];

    $this->repo->shouldReceive('update')->with($justification, $data)->once()->andReturn(new FlightJustification($data));

    $result = $this->service->update($justification, $data);

    expect($result->name)->toBe('Updated name');
});

it('can delete a flight justification', function () {
    $justification = new FlightJustification(['name' => 'To delete']);

    $this->repo->shouldReceive('delete')->with($justification)->once();

    $this->service->delete($justification);

    expect(true)->toBeTrue(); // no exception = success
});
