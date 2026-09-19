<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)
        ->middleware('throttle:60,1')
        ->name('health');

    require __DIR__.'/../app/Modules/Authentication/routes.php';
    require __DIR__.'/../app/Modules/Users/routes.php';
    require __DIR__.'/../app/Modules/Roles/routes.php';
    require __DIR__.'/../app/Modules/Distributors/routes.php';
    require __DIR__.'/../app/Modules/Products/routes.php';
    require __DIR__.'/../app/Modules/Categories/routes.php';
    require __DIR__.'/../app/Modules/Units/routes.php';
    require __DIR__.'/../app/Modules/Warehouses/routes.php';
    require __DIR__.'/../app/Modules/Areas/routes.php';
    require __DIR__.'/../app/Modules/Customers/routes.php';
    require __DIR__.'/../app/Modules/Inventory/routes.php';
    require __DIR__.'/../app/Modules/Invoices/routes.php';
    require __DIR__.'/../app/Modules/Collections/routes.php';
    require __DIR__.'/../app/Modules/Settlements/routes.php';
    require __DIR__.'/../app/Modules/Reports/Custody/routes.php';
    require __DIR__.'/../app/Modules/Notifications/routes.php';
});
