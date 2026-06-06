<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignTargetLocation extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_type',
        'emirate',
        'marketing_area_id',
        'project_id',
        'building_id',
        'include_projects',
        'include_buildings',
    ];

    protected function casts(): array
    {
        return [
            'include_projects'  => 'boolean',
            'include_buildings' => 'boolean',
        ];
    }

    public function marketingArea(): BelongsTo
    {
        return $this->belongsTo(MarketingArea::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function scopeForCampaign(\Illuminate\Database\Eloquent\Builder $query, int $campaignId, string $type): void
    {
        $query->where('campaign_id', $campaignId)->where('campaign_type', $type);
    }
}
