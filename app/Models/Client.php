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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
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
