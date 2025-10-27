<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use App\Services\AuthService;
use App\Services\OperatorService;
use App\Services\AuthServiceInterface;
use Illuminate\Support\ServiceProvider;
use App\Repositories\OperatorRepository;
use App\Services\OperatorServiceInterface;
use App\Repositories\EloquentUserRepository;
use App\Repositories\UserRepositoryInterface;
use Dedoc\Scramble\Support\Generator\OpenApi;
use App\Repositories\OperatorRepositoryInterface;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(\App\Repositories\OperatorRepositoryInterface::class, \App\Repositories\OperatorRepository::class);
        $this->app->bind(\App\Services\OperatorServiceInterface::class, \App\Services\OperatorService::class);
        $this->app->bind(\App\Repositories\AircraftRepositoryInterface::class, \App\Repositories\AircraftRepository::class);
        $this->app->bind(\App\Services\AircraftServiceInterface::class, \App\Services\AircraftService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()
        ->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->components->securitySchemes['bearer'] = SecurityScheme::http('bearer');

            $openApi->security[] = new SecurityRequirement([
                'bearer' => [],
            ]);
        });
    }
}
