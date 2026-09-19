<?php

use App\Modules\Roles\Controllers\PermissionController;
use App\Modules\Roles\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('roles', [RoleController::class, 'index']);
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
    Route::get('permissions', [PermissionController::class, 'index']);
});
