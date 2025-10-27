<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use App\Services\AuthService;
use App\Services\AircraftService;
use App\Services\OperatorService;
use App\Services\AircraftTypeService;
use App\Services\AuthServiceInterface;
use Illuminate\Support\ServiceProvider;
use App\Repositories\AircraftRepository;
use App\Repositories\OperatorRepository;
use App\Services\AircraftServiceInterface;
use App\Services\OperatorServiceInterface;
use App\Repositories\AircraftTypeRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\UserRepositoryInterface;
use Dedoc\Scramble\Support\Generator\OpenApi;
use App\Services\AircraftTypeServiceInterface;
use App\Repositories\AircraftRepositoryInterface;
use App\Repositories\OperatorRepositoryInterface;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Repositories\AircraftTypeRepositoryInterface;
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
        $this->app->bind(OperatorRepositoryInterface::class, OperatorRepository::class);
        $this->app->bind(OperatorServiceInterface::class, OperatorService::class);
        $this->app->bind(AircraftRepositoryInterface::class, AircraftRepository::class);
        $this->app->bind(AircraftServiceInterface::class, AircraftService::class);
        $this->app->bind(AircraftTypeRepositoryInterface::class, AircraftTypeRepository::class);
        $this->app->bind(AircraftTypeServiceInterface::class, AircraftTypeService::class);
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
