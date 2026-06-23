<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Ivr = 'ivr';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Ivr => 'IVR',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
