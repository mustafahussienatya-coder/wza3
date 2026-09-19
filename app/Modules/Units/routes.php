<?php

use App\Modules\Units\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('units', UnitController::class);
});
