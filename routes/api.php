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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Reports routes
Route::prefix('trafic-report')->group(function () {
    Route::get('/yearly/export/{year?}', [
        TraficReportController::class,
        'exportYearlyReport',
    ]);
    Route::get('/monthly/export/{month?}/{year?}', [
        TraficReportController::class,
        'monthlyExportReport',
    ]);
    Route::get('/yearly/{year?}/{regime?}', [
        TraficReportController::class,
        'yearlyReport',
    ]);
    Route::get('/monthly/{month?}/{year?}/{regime?}', [
        TraficReportController::class,
        'monthlyReport',
    ]);
});

Route::prefix('paxbus-report')->group(function () {
    Route::get('/yearly/export/{year}', [
        PaxbusReportController::class,
        'yearlyExportReport',
    ]);
    Route::get('/monthly/export/{month}/{year}', [
        PaxbusReportController::class,
        'monthlyExportReport',
    ]);
    Route::get('/weekly/export/{quinzaine}/{month}/{year}', [
        PaxbusReportController::class,
        'weeklyExportReport',
    ]);
    Route::get('/yearly/{year}/{regime}', [
        PaxbusReportController::class,
        'yearlyReport',
    ]);
    Route::get('/monthly/{month}/{year}/{regime}', [
        PaxbusReportController::class,
        'monthlyReport',
    ]);
    Route::get('/weekly/{quinzaine}/{month}/{year}/{regime}', [
        PaxbusReportController::class,
        'weeklyReport',
    ]);
});

Route::prefix('idef-report')->group(function () {
    Route::get('/yearly/export/{year?}', [
        IdefReportController::class,
        'yearlyExportReport',
    ]);
    Route::get('/monthly/export/{month?}/{year?}', [
        IdefReportController::class,
        'monthlyExportReport',
    ]);
    Route::get('/yearly/{year?}/{regime?}', [
        IdefReportController::class,
        'yearlyReport',
    ]);
    Route::get('/monthly/{month?}/{year?}/{regime?}', [
        IdefReportController::class,
        'monthlyReport',
    ]);
});

// ─── Unified Report routes ────────────────────────────────────────────────────
Route::prefix('report')->group(function () {
    // Aggregated by regime
    Route::get('/monthly/{month}/{year}',        [ReportController::class, 'monthly']);
    Route::get('/yearly/{year}',                 [ReportController::class, 'yearly']);

    // Broken down by operator
    Route::get('/monthly/{month}/{year}/by-operators', [ReportController::class, 'monthlyByOperators']);
    Route::get('/yearly/{year}/by-operators',          [ReportController::class, 'yearlyByOperators']);

    // Exports (stubs registered now, implementations added in next commit)
    Route::get('/monthly/{month}/{year}/export',              [ReportController::class, 'monthlyExport']);
    Route::get('/yearly/{year}/export',                       [ReportController::class, 'yearlyExport']);
    Route::get('/monthly/{month}/{year}/by-operators/export', [ReportController::class, 'monthlyByOperatorsExport']);
    Route::get('/yearly/{year}/by-operators/export',          [ReportController::class, 'yearlyByOperatorsExport']);
    Route::get('/monthly/{month}/{year}/pax-by-operators/export', [ReportController::class, 'monthlyPAXByOperatorsExport']);
    Route::get('/yearly/{year}/pax-by-operators/export',          [ReportController::class, 'yearlyPAXByOperatorsExport']);
});

Route::post('/imports', [ImportController::class, 'store']);

// Operators routes
Route::get('operators/filter', [OperatorController::class, 'filter'])->name('operators.filter');
Route::get('operators/search', [OperatorController::class, 'search'])->name('operators.search');
Route::get('operators/all', [OperatorController::class, 'all'])->name('operators.all');
Route::apiResource('operators', OperatorController::class);

// Aircraft Types routes
Route::get('aircraft-types/filter', [AircraftTypeController::class, 'filter'])->name('aircraft-types.filter');
Route::get('aircraft-types/find/{query}', [AircraftTypeController::class, 'find'])->name('aircraft-types.find');
Route::get('aircraft-types/all', [AircraftTypeController::class, 'all'])->name('aircraft-types.all');
Route::apiResource('aircraft-types', AircraftTypeController::class);

// Aircraft routes
Route::get('aircrafts/filter', [AircraftController::class, 'filter'])->name('aircrafts.filter');
Route::get('aircrafts/search', [AircraftController::class, 'search'])->name('aircrafts.search');
Route::get('aircrafts/all', [AircraftController::class, 'all'])->name('aircrafts.all');
Route::get('operators/{operator}/aircrafts', [AircraftController::class, 'byOperator'])->name('aircrafts.byOperator');
Route::apiResource('aircrafts', AircraftController::class);

// Flight Justifications routes
Route::apiResource('flight-justifications', FlightJustificationController::class);

// Flights routes
Route::get('flights/filter', [FlightController::class, 'filter'])->name('flights.filter');
Route::get('flights/all', [FlightController::class, 'all'])->name('flights.all');
Route::get('flights/daily', [FlightController::class, 'flightsByDate'])->name('flights.daily');
Route::apiResource('flights', FlightController::class);

// IdefFret routes
Route::prefix('idef-frets')->group(function () {
    Route::post('/batch', [IdefFretController::class, 'storeBatch']);
    Route::get('/by-date/{date}', [IdefFretController::class, 'getIdefFretByDate'])->name('idefrets.byDate');
    Route::get('/range/{from}/{to}', [IdefFretController::class, 'getIdefFretsByRange'])->name('idefrets.range');
});
Route::apiResource('idef-frets', IdefFretController::class)->except(['index', 'show']);

// Monthly rate routes
Route::prefix('monthly-rates')->group(function () {
    Route::get('/by-month/{month}/{year}', [MonthlyRateController::class, 'getMonthlyRateByMonth'])->name('monthly-rates.byMonth');
});
Route::apiResource('monthly-rates', MonthlyRateController::class)->except(['show']);