<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppImportType: string
{
    case CampaignResults = 'campaign_results';
    case Unsubscribers = 'unsubscribers';
}
