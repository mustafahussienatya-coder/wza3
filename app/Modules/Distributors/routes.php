<?php

use App\Modules\Distributors\Controllers\CustodyController;
use App\Modules\Distributors\Controllers\DistributorController;
use App\Modules\Distributors\Controllers\DistributorIssueController;
use App\Modules\Distributors\Controllers\My\CustodyController as MyCustodyController;
use App\Modules\Distributors\Controllers\My\DashboardController as MyDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('distributors', [DistributorController::class, 'index']);
    Route::get('distributors/{distributor}', [DistributorController::class, 'show']);
    Route::get('distributors/{distributor}/documents/{type}', [DistributorController::class, 'showDocument'])->name('distributors.documents');
    Route::patch('distributors/{distributor}/status', [DistributorController::class, 'updateStatus']);

    Route::get('distributors/{distributor}/custody', [CustodyController::class, 'index']);
    Route::get('distributors/{distributor}/custody/statement', [CustodyController::class, 'statement']);
    Route::get('distributors/{distributor}/custody/sellable', [CustodyController::class, 'sellable']);

    Route::get('distributor-issues', [DistributorIssueController::class, 'index']);
    Route::post('distributor-issues', [DistributorIssueController::class, 'store']);
    Route::get('distributor-issues/{issue}', [DistributorIssueController::class, 'show']);
    Route::put('distributor-issues/{issue}', [DistributorIssueController::class, 'update']);
    Route::post('distributor-issues/{issue}/submit', [DistributorIssueController::class, 'submit']);
    Route::post('distributor-issues/{issue}/approve', [DistributorIssueController::class, 'approve']);
    Route::post('distributor-issues/{issue}/complete', [DistributorIssueController::class, 'complete']);
    Route::post('distributor-issues/{issue}/approve-disburse', [DistributorIssueController::class, 'approveDisburse']);
    Route::put('distributor-issues/{issue}/correct', [DistributorIssueController::class, 'correct']);
    Route::get('distributor-issues/{issue}/corrections', [DistributorIssueController::class, 'corrections']);
    Route::post('distributor-issues/{issue}/return', [DistributorIssueController::class, 'returnToWarehouse']);
    Route::post('distributor-issues/{issue}/cancel', [DistributorIssueController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'active', 'distributor'])->prefix('distributor/my')->group(function () {
    Route::get('/dashboard', [MyDashboardController::class, 'index']);
    Route::get('/custody', [MyCustodyController::class, 'index']);
    Route::get('/custody/sellable', [MyCustodyController::class, 'sellable']);
    Route::get('/custody/statement', [MyCustodyController::class, 'statement']);
});
