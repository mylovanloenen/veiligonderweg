<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\NeighbourhoodController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Statistische laag
    Route::get('neighbourhoods', [NeighbourhoodController::class, 'index']);
    Route::get('neighbourhoods/categories', [NeighbourhoodController::class, 'categories']);
    Route::get('neighbourhoods/{code}', [NeighbourhoodController::class, 'show']);

    // Live meldingen (lezen is openbaar)
    Route::get('categories', [IncidentController::class, 'categories']);
    Route::get('incidents', [IncidentController::class, 'index']);

    // Accounts
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::post('incidents', [IncidentController::class, 'store'])->middleware('throttle:incidents');
        Route::post('incidents/{incident}/confirm', [IncidentController::class, 'confirm'])->middleware('throttle:votes');
        Route::post('incidents/{incident}/dispute', [IncidentController::class, 'dispute'])->middleware('throttle:votes');
    });
});
