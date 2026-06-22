<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

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

        // Generate https:// URLs behind the production load balancer / TLS terminator, so assets,
        // redirects and Filament links don't downgrade to http.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
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
