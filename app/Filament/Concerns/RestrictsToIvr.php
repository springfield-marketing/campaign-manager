<?php

namespace App\Filament\Concerns;

/**
 * Gate a Filament resource or page to the IVR module (admin + ivr roles). Returning false from
 * canAccess() hides it from the navigation and 403s any direct URL.
 */
trait RestrictsToIvr
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessIvr() ?? false;
    }
}
