<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $modulesPath = app_path('Modules');

        if (! is_dir($modulesPath)) {
            return;
        }

        foreach (glob($modulesPath.'/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
            $moduleName = basename($modulePath);

            $viewsPath = $modulePath.'/Resources/views';
            if (is_dir($viewsPath)) {
                View::addNamespace(Str::lower($moduleName), $viewsPath);
            }

            $routePath = $modulePath.'/Routes/web.php';
            if (is_file($routePath)) {
                Route::middleware('web')->group($routePath);
            }
        }
    }
}
