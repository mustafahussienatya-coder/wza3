<?php

use App\Modules\Warehouses\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::apiResource('warehouses', WarehouseController::class);
});
