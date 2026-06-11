<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'platform',
        'total_messages',
        'sent_count',
        'delivered_count',
        'read_count',
        'replied_count',
        'failed_count',
        'unsubscribed_count',
        'started_at',
        'completed_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_campaign_id');
    }
}
