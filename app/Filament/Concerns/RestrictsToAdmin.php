<?php

namespace App\Filament\Concerns;

/**
 * Gate a Filament resource or page to administrators only. Returning false from canAccess()
 * hides it from the navigation and 403s any direct URL.
 */
trait RestrictsToAdmin
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
