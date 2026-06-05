<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    protected $fillable = [
        'region_id',
        'parent_community_id',
        'name',
        'developer',
        'dld_area_id',
        'dld_master_project_id',
        'zone',
        'is_freehold',
        'project_names',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'parent_community_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Community::class, 'parent_community_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function linkedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_communities')
            ->using(ClientCommunity::class)
            ->withPivot(['id', 'project_id', 'relationship_type', 'confidence_level', 'source', 'notes'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_freehold'   => 'boolean',
            'project_names' => 'array',
        ];
    }
}
