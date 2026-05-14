<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['country_id', 'name'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function communities(): HasMany
    {
        return $this->hasMany(Community::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
