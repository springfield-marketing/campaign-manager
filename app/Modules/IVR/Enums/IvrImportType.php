<?php

namespace App\Modules\IVR\Enums;

enum IvrImportType: string
{
    case RawContacts = 'raw_contacts';
    case CampaignResults = 'campaign_results';
    case Unsubscribers = 'unsubscribers';
}
