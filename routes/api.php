<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameSimulationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Test route to verify API is working
Route::get('/test', function() {
    return response()->json(['status' => 'ok', 'message' => 'API is working']);
});

// Real-time Game Simulation Routes
Route::prefix('simulation')->group(function () {
    Route::post('/start', [GameSimulationController::class, 'startSimulation']);
    Route::post('/update', [GameSimulationController::class, 'processUpdate']);
    Route::get('/state', [GameSimulationController::class, 'getSimulationState']);
    Route::post('/stop', [GameSimulationController::class, 'stopSimulation']);
    Route::post('/game/{gameId}', [GameSimulationController::class, 'simulateGame']);
});

// Game Routes for simulation frontend
Route::get('/games', [GameController::class, 'index']);
Route::get('/games/{id}', [GameController::class, 'show']);
Route::get('/games/{id}/events', [GameController::class, 'getEvents']);
Route::get('/games/{id}/statistics', [GameController::class, 'getStatistics']);
Route::post('/games/schedule-next-week', [GameController::class, 'scheduleNextWeek']); 