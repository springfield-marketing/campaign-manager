<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = [
        'emirate',
        'name',
        'project_id',
        'marketing_area_id',
        'official_area_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function marketingArea(): BelongsTo
    {
        return $this->belongsTo(MarketingArea::class);
    }

    public function officialArea(): BelongsTo
    {
        return $this->belongsTo(OfficialArea::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(Ownership::class);
    }

    public function aliases(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PlaceAlias::class, null, 'entity_type', 'entity_id')
            ->where('entity_type', 'building');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_active', true);
    }
}
