<?php

use App\Modules\Products\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('products/bulk', [ProductController::class, 'bulk']);
    Route::apiResource('products', ProductController::class);
});
