<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AircraftController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\AircraftTypeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');;

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('operators/search', [OperatorController::class, 'search'])->name('operators.search');
Route::apiResource('operators', OperatorController::class);

Route::get('aircraft-types/find/{query}', [AircraftTypeController::class, 'find'])->name('aircraft-types.find');
Route::apiResource('aircraft-types', AircraftTypeController::class);


Route::get('operators/{operator}/aircrafts', [AircraftController::class, 'byOperator'])->name('aircrafts.byOperator');
Route::get('aircrafts/search', [AircraftController::class, 'search'])->name('aircrafts.search');
Route::apiResource('aircrafts', AircraftController::class);

