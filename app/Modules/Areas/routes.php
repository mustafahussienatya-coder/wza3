<?php

use App\Modules\Areas\Controllers\AreaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('areas', AreaController::class);
});
