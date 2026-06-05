<?php

namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClientCommunity extends Pivot
{
    public $table = 'client_communities';

    public $incrementing = true;

    protected $fillable = [
        'client_id',
        'community_id',
        'project_id',
        'relationship_type',
        'confidence_level',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'relationship_type' => RelationshipType::class,
            'confidence_level'  => ConfidenceLevel::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
