<?php

use App\Repositories\UserRepositoryInterface;
use App\Services\AuthService;
use Illuminate\Hashing\HashManager;

beforeEach(function () {
    $this->users = \Mockery::mock(UserRepositoryInterface::class);
    $this->hash = \Mockery::mock(HashManager::class);
    $this->service = new AuthService($this->users, $this->hash);
});

afterEach(function () {
    \Mockery::close();
});

it('authenticates with valid credentials', function () {
    $username = 'jdoe';
    $password = 'secret';
    // mock a User instance (type-hint compatible)
    $user = \Mockery::mock(\App\Models\User::class);
    $user->shouldReceive('createToken')->with('api-token')->andReturn((object) ['plainTextToken' => 'token']);
    // Eloquent model property access ($user->password) calls getAttribute('password')
    $user->shouldReceive('getAttribute')->with('password')->andReturn('hashed_password');

    $this->users->shouldReceive('findByUsername')->with($username)->andReturn($user);
    // don't rely on property access; accept any second arg for the hash check
    $this->hash->shouldReceive('check')->with($password, \Mockery::any())->andReturn(true);

    $result = $this->service->authenticate($username, $password);

    expect($result)->toBeArray();
    expect(isset($result['token']))->toBeTrue();
});

it('returns null on invalid credentials', function () {
    $username = 'unknown';
    $password = 'wrong';

    $this->users->shouldReceive('findByUsername')->with($username)->andReturn(null);

    $result = $this->service->authenticate($username, $password);

    expect($result)->toBeNull();
});

it('logout deletes current token', function () {
    $user = \Mockery::mock(\App\Models\User::class);
    $token = (object) ['id' => 123];

    $user->shouldReceive('currentAccessToken')->andReturn($token);
    // tokens()->where('id', ...)->delete()
    $tokensRelation = \Mockery::mock();
    $tokensRelation->shouldReceive('where')->with('id', $token->id)->andReturnSelf();
    $tokensRelation->shouldReceive('delete')->andReturn(1);
    $user->shouldReceive('tokens')->andReturn($tokensRelation);

    $ok = $this->service->logout($user);

    expect($ok)->toBeTrue();
});
