<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            // In production only capture failures and slow queries (>500 ms)
            return $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag()
                || ($entry->type === 'query' && ($entry->content['slow'] ?? false));
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * In local environments all authenticated users may access Telescope.
     * In production, restrict to emails listed in TELESCOPE_ALLOWED_EMAILS
     * (comma-separated) or fall back to allowing any authenticated user.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            if (app()->environment('local')) {
                return true;
            }

            $allowed = array_filter(
                array_map('trim', explode(',', env('TELESCOPE_ALLOWED_EMAILS', '')))
            );

            return empty($allowed) || in_array($user->email, $allowed, true);
        });
    }
}
