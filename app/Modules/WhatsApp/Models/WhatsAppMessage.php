<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_campaign_id',
        'whatsapp_import_id',
        'client_phone_number_id',
        'scheduled_at',
        'template_name',
        'delivery_status',
        'failure_reason',
        'has_quick_replies',
        'quick_reply_1',
        'quick_reply_2',
        'quick_reply_3',
        'clicked',
        'retried',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'has_quick_replies' => 'boolean',
            'clicked' => 'boolean',
            'retried' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id', 'id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(WhatsAppImport::class, 'whatsapp_import_id', 'id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class, 'client_phone_number_id');
    }
}
