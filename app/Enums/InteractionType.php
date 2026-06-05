<?php

namespace App\Enums;

enum InteractionType: string
{
    case IvrCampaign    = 'ivr_campaign';
    case WhatsAppCampaign = 'whatsapp_campaign';
    case AgentUpload    = 'agent_upload';
    case ManualEntry    = 'manual_entry';
    case Import         = 'import';
    case Note           = 'note';
    case PhoneCall      = 'phone_call';

    public function label(): string
    {
        return match ($this) {
            self::IvrCampaign       => 'IVR Campaign',
            self::WhatsAppCampaign  => 'WhatsApp Campaign',
            self::AgentUpload       => 'Agent Upload',
            self::ManualEntry       => 'Manual Entry',
            self::Import            => 'Import',
            self::Note              => 'Note',
            self::PhoneCall         => 'Phone Call',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value',
        );
    }
}
