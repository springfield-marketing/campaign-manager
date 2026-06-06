<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportStaging extends Model
{
    protected $table = 'import_staging';

    protected $fillable = [
        'batch_id',
        'name',
        'phone',
        'email',
        'country_iso',
        'emirate',
        'raw_official_area',
        'raw_marketing_area',
        'raw_project_name',
        'raw_building_name',
        'raw_unit_reference',
        'official_area_id',
        'marketing_area_id',
        'project_id',
        'building_id',
        'relationship_type',
        'confidence_level',
        'source',
        'status',
        'status_reason',
    ];

    public const STATUS_PENDING      = 'pending';
    public const STATUS_MATCHED      = 'matched';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_REJECTED     = 'rejected';

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

    public function reviewItem(): HasOne
    {
        return $this->hasOne(ImportReviewQueue::class, 'staging_id');
    }

    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeNeedsReview(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', self::STATUS_NEEDS_REVIEW);
    }
}
