<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PlaceAlias extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'alias_name',
        'source',
        'confidence_level',
    ];

    /** Find the entity ID for a given alias, case-insensitively. */
    public static function resolveId(string $entityType, string $alias): ?int
    {
        return static::query()
            ->where('entity_type', $entityType)
            ->whereRaw('lower(alias_name) = lower(?)', [trim($alias)])
            ->value('entity_id');
    }

    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('entity_type', $type);
    }
}
