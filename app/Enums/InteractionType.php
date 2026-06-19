<?php

namespace App\Enums;

enum InteractionType: string
{
    case IvrCampaign = 'ivr_campaign';
    case WhatsAppCampaign = 'whatsapp_campaign';
    case AgentUpload = 'agent_upload';
    case ManualEntry = 'manual_entry';
    case Import = 'import';
    case Note = 'note';
    case PhoneCall = 'phone_call';
}
