<?php

use App\Modules\Collections\Controllers\CollectionController;
use App\Modules\Collections\Controllers\My\CollectionController as MyCollectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('collections', [CollectionController::class, 'index']);
    Route::get('collections/{collection}', [CollectionController::class, 'show']);
    Route::post('collections', [CollectionController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'active', 'distributor'])->prefix('distributor/my')->group(function () {
    Route::get('collections', [MyCollectionController::class, 'index']);
    Route::get('collections/{collection}', [MyCollectionController::class, 'show']);
    Route::post('collections', [MyCollectionController::class, 'store']);
});
