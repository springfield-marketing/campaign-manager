<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ownership extends Model
{
    protected $fillable = [
        'client_id',
        'emirate',
        'official_area_id',
        'marketing_area_id',
        'project_id',
        'building_id',
        'unit_reference',
        'relationship_type',
        'confidence_level',
        'last_source_name',
        'source_names',
        'first_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_names'       => 'array',
            'first_confirmed_at' => 'datetime',
        ];
    }

    private const CONFIDENCE_RANK = ['low' => 1, 'medium' => 2, 'high' => 3];

    /** Returns the higher of two confidence levels, or the non-null one. */
    public static function higherConfidence(?string $a, ?string $b): ?string
    {
        if ($a === null) return $b;
        if ($b === null) return $a;
        return (self::CONFIDENCE_RANK[$a] ?? 0) >= (self::CONFIDENCE_RANK[$b] ?? 0) ? $a : $b;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function officialArea(): BelongsTo
    {
        return $this->belongsTo(OfficialArea::class);
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

    public function scopeOwners(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('relationship_type', 'owner');
    }

    public function scopeForEmirate(\Illuminate\Database\Eloquent\Builder $query, string $emirate): void
    {
        $query->where('emirate', $emirate);
    }

    public function scopeForMarketingArea(\Illuminate\Database\Eloquent\Builder $query, int $marketingAreaId): void
    {
        $query->where('marketing_area_id', $marketingAreaId);
    }
}
