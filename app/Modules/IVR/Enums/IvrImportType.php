<?php

namespace App\Modules\IVR\Enums;

use Filament\Support\Contracts\HasLabel;

enum IvrImportType: string implements HasLabel
{
    case RawContacts = 'raw_contacts';
    case CampaignResults = 'campaign_results';
    case Unsubscribers = 'unsubscribers';

    public function getLabel(): string
    {
        return match ($this) {
            self::RawContacts => 'Raw Contacts',
            self::CampaignResults => 'Campaign Results',
            self::Unsubscribers => 'Do Not Call List',
        };
    }
}
