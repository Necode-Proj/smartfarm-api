<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\WateringController;
use App\Http\Controllers\Api\DashboardController;

// ─── Public Routes ─────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Protected Routes ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Blocks
    Route::get('/blocks', [BlockController::class, 'index']);
    Route::get('/blocks/{id}', [BlockController::class, 'show']);
    Route::get('/blocks/{id}/readings', [BlockController::class, 'readings']);

    // Watering
    Route::post('/blocks/{id}/water', [WateringController::class, 'water']);
    Route::get('/watering-logs', [WateringController::class, 'logs']);
});
