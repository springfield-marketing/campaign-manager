<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingArea extends Model
{
    protected $fillable = [
        'emirate',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function officialAreas(): BelongsToMany
    {
        return $this->belongsToMany(OfficialArea::class, 'marketing_area_official_areas')
            ->withPivot('confidence_level', 'notes')
            ->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(Ownership::class);
    }

    public function campaignTargetLocations(): HasMany
    {
        return $this->hasMany(CampaignTargetLocation::class);
    }

    public function aliases(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PlaceAlias::class, null, 'entity_type', 'entity_id')
            ->where('entity_type', 'marketing_area');
    }

    public function scopeForEmirate(\Illuminate\Database\Eloquent\Builder $query, string $emirate): void
    {
        $query->where('emirate', $emirate);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_active', true);
    }
}
