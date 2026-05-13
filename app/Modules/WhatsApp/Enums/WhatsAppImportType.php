<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppImportType: string
{
    case RawContacts = 'raw_contacts';
    case CampaignResults = 'campaign_results';
    case Unsubscribers = 'unsubscribers';
}
