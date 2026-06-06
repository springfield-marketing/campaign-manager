<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * In local environments all authenticated users may access Horizon.
     * In production, restrict to emails listed in HORIZON_ALLOWED_EMAILS
     * (comma-separated) or fall back to allowing any authenticated user.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            if (app()->environment('local')) {
                return true;
            }

            $allowed = array_filter(
                array_map('trim', explode(',', env('HORIZON_ALLOWED_EMAILS', '')))
            );

            return empty($allowed) || in_array($user->email, $allowed, true);
        });
    }
}
