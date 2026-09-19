<?php

use App\Modules\Customers\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('customers/summary', [CustomerController::class, 'summary']);
    Route::post('customers/{customer}/transfer', [CustomerController::class, 'transfer']);
    Route::patch('customers/{customer}/status', [CustomerController::class, 'changeStatus']);
    Route::post('customers/{customer}/opening-balance', [CustomerController::class, 'adjustOpeningBalance']);
    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger']);
    Route::apiResource('customers', CustomerController::class);
});
