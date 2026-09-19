<?php

use App\Modules\Reports\Custody\Controllers\CustodyReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'can:custody.report'])->prefix('reports/custody')->group(function () {
    Route::get('summary', [CustodyReportController::class, 'summary'])->name('reports.custody.summary');
    Route::get('by-distributor', [CustodyReportController::class, 'byDistributor'])->name('reports.custody.by_distributor');
    Route::get('by-distributor/{distributorId}', [CustodyReportController::class, 'distributorDetail'])->name('reports.custody.distributor_detail');
    Route::get('by-product', [CustodyReportController::class, 'byProduct'])->name('reports.custody.by_product');
    Route::get('product-tracker', [CustodyReportController::class, 'productTracker'])->name('reports.custody.product_tracker');
});
