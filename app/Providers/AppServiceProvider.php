<?php

namespace App\Providers;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // php artisan serve ignores .user.ini — raise the web process to a 256M floor here.
        // Only RAISE: never lower an already-higher limit, or this clobbers contexts that set
        // their own (queue workers, the import processors, static-analysis tooling).
        if ($this->currentMemoryLimitBytes() < 256 * 1024 * 1024) {
            ini_set('memory_limit', '256M');
        }

        $this->configureRateLimiting();
    }

    /** Current memory_limit in bytes; PHP_INT_MAX when unlimited (-1). */
    private function currentMemoryLimitBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function configureRateLimiting(): void
    {
        // File uploads: max 20 per user per minute
        RateLimiter::for('file-uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Heavy exports (CSV streaming): max 10 per user per minute
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
