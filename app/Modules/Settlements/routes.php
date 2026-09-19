<?php

use App\Modules\Settlements\Controllers\My\ResponsibilityController as MyResponsibilityController;
use App\Modules\Settlements\Controllers\My\SettlementController as MySettlementController;
use App\Modules\Settlements\Controllers\My\StatementController as MyStatementController;
use App\Modules\Settlements\Controllers\ResponsibilityController;
use App\Modules\Settlements\Controllers\SettlementController;
use App\Modules\Settlements\Controllers\StatementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('settlements', [SettlementController::class, 'index']);
    Route::post('settlements', [SettlementController::class, 'store']);
    Route::get('settlements/{settlement}', [SettlementController::class, 'show']);

    Route::get('distributors/{distributor}/responsibility', [ResponsibilityController::class, 'show']);
    Route::get('distributors/{distributor}/statement', [StatementController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'active', 'distributor'])->prefix('distributor/my')->group(function () {
    Route::get('settlements', [MySettlementController::class, 'index']);
    Route::post('settlements', [MySettlementController::class, 'store']);
    Route::get('settlements/{settlement}', [MySettlementController::class, 'show']);

    Route::get('responsibility', [MyResponsibilityController::class, 'show']);
    Route::get('statement', [MyStatementController::class, 'index']);
});
