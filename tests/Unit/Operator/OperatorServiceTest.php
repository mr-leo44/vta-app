<?php

namespace Tests\Unit\Operator;

use Mockery;
use Tests\TestCase;
use App\Models\Operator;
use App\Services\OperatorService;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\OperatorRepositoryInterface;

beforeEach(function () {
    $this->repo = Mockery::mock(OperatorRepositoryInterface::class);
    $this->service = new OperatorService($this->repo);
});

afterEach(function () {
    Mockery::close();
});

it('can return all operators', function () {
$operators = new Collection([new Operator(['name' => 'Congo Airways'])]);
$this->repo->shouldReceive('all')->once()->andReturn($operators);

    $result = $this->service->getAll();

    expect($result)->toBe($operators);
});

it('can search operator by name or IATA code', function () {
    $nameOperator = new Operator(['name' => 'Congo Airways']);
    $this->repo->shouldReceive('findByNameOrIata')->with('Congo')->once()->andReturn($nameOperator);

    $result = $this->service->findByNameOrIata('Congo');

    expect($result)->toBe($nameOperator);

    $iataOperator = new Operator(['iata_code' => '8Z']);
    $this->repo->shouldReceive('findByNameOrIata')->with('8Z')->once()->andReturn($iataOperator);

    $result = $this->service->findByNameOrIata('8Z');

    expect($result)->toBe($iataOperator);
});
