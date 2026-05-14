<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'country',
        'nationality',
        'community',
        'resident',
        'city',
        'gender',
        'interest',
        'metadata',
        'country_id',
        'region_id',
        'community_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // Named geoCountry/geoCommunity to avoid colliding with the legacy
    // text columns (country, community) that still exist during Phase 3.
    // Rename to country()/community() once those columns are dropped in Phase 4.
    public function geoCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function geoCommunity(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(ClientPhoneNumber::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ClientSource::class);
    }
}
