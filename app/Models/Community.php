<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    protected $fillable = ['region_id', 'parent_community_id', 'name'];

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
}
