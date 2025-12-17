<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FlightController;
use App\Http\Controllers\Api\AircraftController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\AircraftTypeController;
use App\Http\Controllers\Api\TraficReportController;
use App\Http\Controllers\Api\FlightJustificationController;
use App\Http\Controllers\Api\PaxbusReportController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route JSON
Route::get('/trafic-report/{year?}/{regime?}', [
    TraficReportController::class, 
    'yearlyReport'
]);

Route::get('/trafic-report/export/{year?}/{regime?}', [
    TraficReportController::class, 
    'exportYearlyReport'
]);

Route::get('/trafic-report/{month?}/{year?}/{regime?}', [
    TraficReportController::class, 
    'monthlyReport'
]);

// Route Export Excel

Route::get('/trafic-report/export/{month?}/{year?}/{regime?}', [
    TraficReportController::class, 
    'exportMonthlyReport'
]);

Route::get('/paxbus-report/{month?}/{year?}/international', [
    PaxbusReportController::class, 
    'monthlyInternationalReport'
]);

Route::get('/paxbus-report/export/{month?}/{year?}/international', [
    PaxbusReportController::class, 
    'exportMonthlyReport'
]);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');;

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('operators/search', [OperatorController::class, 'search'])->name('operators.search');
Route::get('operators/all', [OperatorController::class, 'all'])->name('operators.all');
Route::apiResource('operators', OperatorController::class);

Route::get('aircraft-types/find/{query}', [AircraftTypeController::class, 'find'])->name('aircraft-types.find');
Route::get('aircraft-types/all', [AircraftTypeController::class, 'all'])->name('aircraft-types.all');
Route::apiResource('aircraft-types', AircraftTypeController::class);


Route::get('operators/{operator}/aircrafts', [AircraftController::class, 'byOperator'])->name('aircrafts.byOperator');
Route::get('aircrafts/search', [AircraftController::class, 'search'])->name('aircrafts.search');
Route::get('aircrafts/all', [AircraftController::class, 'all'])->name('aircrafts.all');
Route::apiResource('aircrafts', AircraftController::class);

Route::apiResource('flight-justifications', FlightJustificationController::class);
Route::get('flights/all', [FlightController::class, 'all'])->name('flights.all');
Route::get('/flights/daily', [FlightController::class, 'flightsByDate']);
Route::apiResource('flights', FlightController::class);
