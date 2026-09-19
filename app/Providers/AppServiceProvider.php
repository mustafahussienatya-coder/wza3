<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerModuleRoutes();
    }

    private function registerModuleRoutes(): void
    {
        $modulePath = app_path('Modules');

        if (! is_dir($modulePath)) {
            return;
        }

        foreach (File::directories($modulePath) as $moduleDir) {
            $routeFile = $moduleDir.DIRECTORY_SEPARATOR.'routes.php';

            if (File::exists($routeFile)) {
                Route::middleware('api')
                    ->prefix('api/v1')
                    ->group($routeFile);
            }
        }
    }
}
