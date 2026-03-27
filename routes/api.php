<?php

use App\Http\Controllers\Api\AircraftController;
use App\Http\Controllers\Api\AircraftTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\FlightJustificationController;
use App\Http\Controllers\Api\IdefFretController;
use App\Http\Controllers\Api\IdefReportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\MonthlyRateController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\PaxbusReportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TraficReportController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPermissionController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Routes publiques (sans authentification)
// ─────────────────────────────────────────────────────────────────────────────

Route::post('/login',  [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// ─────────────────────────────────────────────────────────────────────────────
// Routes protégées — token Sanctum obligatoire
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // ── Profil utilisateur connecté (permissions incluses → store Pinia) ──
    Route::get('/user', [UserController::class, 'me']);

    // ─────────────────────────────────────────────────────────────────────
    // Gestion des utilisateurs — admin uniquement
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('users')->group(function () {
        Route::get('/',    [UserController::class, 'index']);
        Route::post('/',   [UserController::class, 'store']);
        Route::put('/{user}',    [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);

        // Assigne une fonction (sync rôle Spatie automatique)
        Route::post('/{user}/assign-function', [UserController::class, 'assignFunction']);

        // ── Overrides de permissions ──────────────────────────────────────
        Route::prefix('/{user}/permissions')->group(function () {
            Route::get('/',              [UserPermissionController::class, 'index']);
            Route::post('/grant',        [UserPermissionController::class, 'grant']);
            Route::post('/revoke',       [UserPermissionController::class, 'revoke']);
            Route::delete('/{permission}', [UserPermissionController::class, 'destroy'])
                 ->where('permission', '.+'); // la permission contient un point (ex: flight.create)
        });
    });

    // ─────────────────────────────────────────────────────────────────────
    // Audit — admin uniquement
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('audit')->middleware('permission:user.viewAny')->group(function () {
        Route::get('/',        [AuditController::class, 'index']);
        Route::get('/stats',   [AuditController::class, 'stats']);
        Route::get('/actors',  [AuditController::class, 'actors']);
    });

    // ─────────────────────────────────────────────────────────────────────
    // Vols — permissions granulaires via FlightPolicy
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('flights')->group(function () {
        // Lecture (flight.viewAny)
        Route::get('/filter', [FlightController::class, 'filter'])->name('flights.filter');
        Route::get('/all',    [FlightController::class, 'all'])->name('flights.all');
        Route::get('/daily',  [FlightController::class, 'flightsByDate'])->name('flights.daily');

        // Validation (flight.validate — admin uniquement via FlightPolicy)
        Route::post('/{flight}/validate', [FlightController::class, 'validateFlight'])
             ->name('flights.validate');
    });

    Route::apiResource('flights', FlightController::class);
    // index   → FlightPolicy::viewAny  (flight.viewAny)
    // show    → FlightPolicy::view     (flight.view)
    // store   → FlightPolicy::create   (flight.create)
    // update  → FlightPolicy::update   (flight.updateOwn | flight.updateAny)
    // destroy → FlightPolicy::delete   (flight.deleteOwn | flight.deleteAny)

    // ─────────────────────────────────────────────────────────────────────
    // Opérateurs
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('permission:operator.viewAny')->group(function () {
        Route::get('operators/filter', [OperatorController::class, 'filter'])->name('operators.filter');
        Route::get('operators/search', [OperatorController::class, 'search'])->name('operators.search');
        Route::get('operators/all',    [OperatorController::class, 'all'])->name('operators.all');
    });

    Route::apiResource('operators', OperatorController::class);
    // index   → permission:operator.viewAny (via middleware Spatie sur la resource)
    // show    → permission:operator.view
    // store   → permission:operator.create
    // update  → permission:operator.update
    // destroy → permission:operator.delete

    // ─────────────────────────────────────────────────────────────────────
    // Types d'avion
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('permission:aircraftType.viewAny')->group(function () {
        Route::get('aircraft-types/filter',       [AircraftTypeController::class, 'filter'])->name('aircraft-types.filter');
        Route::get('aircraft-types/find/{query}', [AircraftTypeController::class, 'find'])->name('aircraft-types.find');
        Route::get('aircraft-types/all',          [AircraftTypeController::class, 'all'])->name('aircraft-types.all');
    });

    Route::apiResource('aircraft-types', AircraftTypeController::class);

    // ─────────────────────────────────────────────────────────────────────
    // Avions
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('permission:aircraft.viewAny')->group(function () {
        Route::get('aircrafts/filter', [AircraftController::class, 'filter'])->name('aircrafts.filter');
        Route::get('aircrafts/search', [AircraftController::class, 'search'])->name('aircrafts.search');
        Route::get('aircrafts/all',    [AircraftController::class, 'all'])->name('aircrafts.all');
        Route::get('operators/{operator}/aircrafts', [AircraftController::class, 'byOperator'])->name('aircrafts.byOperator');
    });

    Route::apiResource('aircrafts', AircraftController::class);

    // ─────────────────────────────────────────────────────────────────────
    // Justifications de vol
    // ─────────────────────────────────────────────────────────────────────

    Route::apiResource('flight-justifications', FlightJustificationController::class);

    // ─────────────────────────────────────────────────────────────────────
    // Import de fichiers — admin uniquement (files.import)
    // ─────────────────────────────────────────────────────────────────────

    Route::post('/imports', [ImportController::class, 'store'])
         ->middleware('permission:files.import');

    // ─────────────────────────────────────────────────────────────────────
    // Rapports — lecture (report.view) et export (report.export)
    // ─────────────────────────────────────────────────────────────────────

    Route::middleware('permission:report.view')->group(function () {
        // Trafic
        Route::prefix('trafic-report')->group(function () {
            Route::get('/yearly/{year?}/{regime?}',        [TraficReportController::class, 'yearlyReport']);
            Route::get('/monthly/{month?}/{year?}/{regime?}', [TraficReportController::class, 'monthlyReport']);
        });

        // PAX Bus
        Route::prefix('paxbus-report')->group(function () {
            Route::get('/yearly/{year}/{regime}',              [PaxbusReportController::class, 'yearlyReport']);
            Route::get('/monthly/{month}/{year}/{regime}',     [PaxbusReportController::class, 'monthlyReport']);
            Route::get('/weekly/{quinzaine}/{month}/{year}/{regime}', [PaxbusReportController::class, 'weeklyReport']);
        });

        // IDEF
        Route::prefix('idef-report')->group(function () {
            Route::get('/yearly/{year?}/{regime?}',           [IdefReportController::class, 'yearlyReport']);
            Route::get('/monthly/{month?}/{year?}/{regime?}', [IdefReportController::class, 'monthlyReport']);
        });

        // Rapport unifié
        Route::prefix('report')->group(function () {
            Route::get('/monthly/{month}/{year}',             [ReportController::class, 'monthly']);
            Route::get('/yearly/{year}',                      [ReportController::class, 'yearly']);
            Route::get('/monthly/{month}/{year}/by-operators',[ReportController::class, 'monthlyByOperators']);
            Route::get('/yearly/{year}/by-operators',         [ReportController::class, 'yearlyByOperators']);
        });
    });

    Route::middleware('permission:report.export')->group(function () {
        // Trafic exports
        Route::prefix('trafic-report')->group(function () {
            Route::get('/yearly/export/{year?}',              [TraficReportController::class, 'exportYearlyReport']);
            Route::get('/monthly/export/{month?}/{year?}',    [TraficReportController::class, 'monthlyExportReport']);
        });

        // PAX Bus exports
        Route::prefix('paxbus-report')->group(function () {
            Route::get('/yearly/export/{year}',               [PaxbusReportController::class, 'yearlyExportReport']);
            Route::get('/monthly/export/{month}/{year}',      [PaxbusReportController::class, 'monthlyExportReport']);
            Route::get('/weekly/export/{quinzaine}/{month}/{year}', [PaxbusReportController::class, 'weeklyExportReport']);
        });

        // IDEF exports
        Route::prefix('idef-report')->group(function () {
            Route::get('/yearly/export/{year?}',              [IdefReportController::class, 'yearlyExportReport']);
            Route::get('/monthly/export/{month?}/{year?}',    [IdefReportController::class, 'monthlyExportReport']);
        });

        // Rapport unifié exports
        Route::prefix('report')->group(function () {
            Route::get('/monthly/{month}/{year}/export',                       [ReportController::class, 'monthlyExport']);
            Route::get('/yearly/{year}/export',                                [ReportController::class, 'yearlyExport']);
            Route::get('/monthly/{month}/{year}/by-operators/export',          [ReportController::class, 'monthlyByOperatorsExport']);
            Route::get('/yearly/{year}/by-operators/export',                   [ReportController::class, 'yearlyByOperatorsExport']);
            Route::get('/monthly/{month}/{year}/pax-by-operators/export',      [ReportController::class, 'monthlyPAXByOperatorsExport']);
            Route::get('/yearly/{year}/pax-by-operators/export',               [ReportController::class, 'yearlyPAXByOperatorsExport']);
        });
    });

    // ─────────────────────────────────────────────────────────────────────
    // IDEF Frets — admin / manager
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('idef-frets')->group(function () {
        Route::post('/batch',          [IdefFretController::class, 'storeBatch']);
        Route::get('/by-date/{date}',  [IdefFretController::class, 'getIdefFretByDate'])->name('idefrets.byDate');
        Route::get('/range/{from}/{to}', [IdefFretController::class, 'getIdefFretsByRange'])->name('idefrets.range');
    });

    Route::apiResource('idef-frets', IdefFretController::class)->except(['index', 'show']);

    // ─────────────────────────────────────────────────────────────────────
    // Taux mensuels
    // ─────────────────────────────────────────────────────────────────────

    Route::prefix('monthly-rates')->group(function () {
        Route::get('/by-month/{month}/{year}', [MonthlyRateController::class, 'getMonthlyRateByMonth'])->name('monthly-rates.byMonth');
    });

    Route::apiResource('monthly-rates', MonthlyRateController::class)->except(['show']);
});
