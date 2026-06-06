<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportReviewQueue extends Model
{
    protected $table = 'import_review_queue';

    protected $fillable = [
        'batch_id',
        'staging_id',
        'name',
        'phone',
        'email',
        'emirate',
        'raw_official_area',
        'raw_marketing_area',
        'raw_project_name',
        'raw_building_name',
        'raw_unit_reference',
        'relationship_type',
        'confidence_level',
        'source',
        'suggested_official_area_id',
        'suggested_marketing_area_id',
        'suggested_project_id',
        'suggested_building_id',
        'issue_reason',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public const RESOLUTION_PENDING  = 'pending';
    public const RESOLUTION_APPROVED = 'approved';
    public const RESOLUTION_REJECTED = 'rejected';

    public function staging(): BelongsTo
    {
        return $this->belongsTo(ImportStaging::class, 'staging_id');
    }

    public function suggestedOfficialArea(): BelongsTo
    {
        return $this->belongsTo(OfficialArea::class, 'suggested_official_area_id');
    }

    public function suggestedMarketingArea(): BelongsTo
    {
        return $this->belongsTo(MarketingArea::class, 'suggested_marketing_area_id');
    }

    public function suggestedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'suggested_project_id');
    }

    public function suggestedBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'suggested_building_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('resolution', self::RESOLUTION_PENDING);
    }
}
