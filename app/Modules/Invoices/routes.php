<?php

use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\Invoices\Controllers\My\InvoiceController as MyInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('invoices/{invoice}/confirm', [InvoiceController::class, 'confirm']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'active', 'distributor'])->prefix('distributor/my')->group(function () {
    Route::get('invoices', [MyInvoiceController::class, 'index']);
    Route::post('invoices', [MyInvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [MyInvoiceController::class, 'show']);
    Route::get('invoices/{invoice}/print', [MyInvoiceController::class, 'print']);
    Route::put('invoices/{invoice}', [MyInvoiceController::class, 'update']);
    Route::post('invoices/{invoice}/confirm', [MyInvoiceController::class, 'confirm']);
    Route::post('invoices/{invoice}/cancel', [MyInvoiceController::class, 'cancel']);
});
