<?php

use App\Modules\Inventory\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/stock-on-hand', [InventoryController::class, 'stockOnHand']);
    Route::get('inventory/batches', [InventoryController::class, 'batches']);
    Route::get('inventory/movements', [InventoryController::class, 'movements']);
    Route::post('inventory/stock-in', [InventoryController::class, 'storeStockIn']);
    Route::post('inventory/bulk-stock-in', [InventoryController::class, 'bulkStoreStockIn']);
    Route::post('inventory/movements/{stockMovement}/correct', [InventoryController::class, 'correctMovement']);
});
