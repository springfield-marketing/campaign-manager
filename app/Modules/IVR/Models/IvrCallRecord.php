<?php

namespace App\Modules\IVR\Models;

use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrCallRecord extends Model
{
    protected $fillable = [
        'ivr_campaign_id',
        'ivr_import_id',
        'client_phone_number_id',
        'external_call_uuid',
        'call_time',
        'call_direction',
        'call_status',
        'customer_status',
        'agent_status',
        'total_duration_seconds',
        'talk_time_seconds',
        'call_action',
        'dtmf_extensions',
        'dtmf_outcome',
        'queue',
        'disposition',
        'sub_disposition',
        'hangup_by',
        'ivr_id',
        'credits_deducted',
        'follow_up_date',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'call_time' => 'datetime',
            'follow_up_date' => 'datetime',
            'dtmf_extensions' => 'array',
            'credits_deducted' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(IvrCampaign::class, 'ivr_campaign_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(IvrImport::class, 'ivr_import_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class, 'client_phone_number_id');
    }
}
