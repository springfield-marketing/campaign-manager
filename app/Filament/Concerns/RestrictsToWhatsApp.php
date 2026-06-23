<?php

namespace App\Filament\Concerns;

/**
 * Gate a Filament resource or page to the WhatsApp module (admin + whatsapp roles). Returning
 * false from canAccess() hides it from the navigation and 403s any direct URL.
 */
trait RestrictsToWhatsApp
{
    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessWhatsApp() ?? false;
    }
}
