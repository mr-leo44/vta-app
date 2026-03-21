<?php

namespace App\Providers;

use App\Models\Aircraft;
use App\Models\AircraftType;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Policies\FlightPolicy;
use App\Policies\UserPolicy;
use App\Repositories\AircraftRepository;
use App\Repositories\AircraftRepositoryInterface;
use App\Repositories\AircraftTypeRepository;
use App\Repositories\AircraftTypeRepositoryInterface;
use App\Repositories\EloquentUserRepository;
use App\Repositories\FlightJustificationRepository;
use App\Repositories\FlightJustificationRepositoryInterface;
use App\Repositories\FlightRepository;
use App\Repositories\FlightRepositoryInterface;
use App\Repositories\OperatorRepository;
use App\Repositories\OperatorRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use App\Services\AircraftService;
use App\Services\AircraftServiceInterface;
use App\Services\AircraftTypeService;
use App\Services\AircraftTypeServiceInterface;
use App\Services\AuthService;
use App\Services\AuthServiceInterface;
use App\Services\OperatorService;
use App\Services\OperatorServiceInterface;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        $this->app->bind(FlightJustificationRepositoryInterface::class, FlightJustificationRepository::class);
        $this->app->bind(FlightRepositoryInterface::class, FlightRepository::class);
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

        // ── Enregistrement des Policies ───────────────────────────────────
        Gate::policy(Flight::class, FlightPolicy::class);
        Gate::policy(User::class,   UserPolicy::class);

        // ── Super-admin Gate (court-circuit) ──────────────────────────────
        // Un utilisateur ayant le rôle "admin" passe tous les Gate checks
        // SAUF les checks explicitement refusés par une Policy (ex : auto-delete).
        // Ce Gate::before() est évalué AVANT toute Policy.
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('admin')) {
                return true; // null = continuer vers la Policy ; true = bypass
            }
            return null;
        });

        // ── Observers d'audit ────────────────────────────────────────────
        Flight::observe(AuditObserver::class);
        Aircraft::observe(AuditObserver::class);
        AircraftType::observe(AuditObserver::class);
        Operator::observe(AuditObserver::class);
    }
}
