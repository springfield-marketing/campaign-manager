<?php

namespace App\Providers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn (): ModuleRegistry => new ModuleRegistry(
            config('modules', []),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
