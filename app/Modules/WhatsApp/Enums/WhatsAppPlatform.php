<?php

namespace App\Modules\WhatsApp\Enums;

use Filament\Support\Contracts\HasLabel;

enum WhatsAppPlatform: string implements HasLabel
{
    case Wati1    = 'wati_1';
    case Wati2    = 'wati_2';
    case Wati3    = 'wati_3';
    case Wati4    = 'wati_4';
    case Gupshup1 = 'gupshup_1';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wati1    => 'Wati 1',
            self::Wati2    => 'Wati 2',
            self::Wati3    => 'Wati 3',
            self::Wati4    => 'Wati 4',
            self::Gupshup1 => 'Gupshup 1',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->getLabel()])
            ->all();
    }
}
