<?php

namespace App\Modules\IVR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrCampaign extends Model
{
    protected $fillable = [
        'external_campaign_id',
        'name',
        'platform',
        'state',
        'total_calls',
        'answered_calls',
        'unanswered_calls',
        'leads_count',
        'more_info_count',
        'unsubscribed_count',
        'credits_used',
        'started_at',
        'completed_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'credits_used' => 'decimal:2',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    public function callRecords(): HasMany
    {
        return $this->hasMany(IvrCallRecord::class);
    }
}
