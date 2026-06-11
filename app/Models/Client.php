<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'country_iso',
        'emirate',
        'nationality',
        'gender',
        'interest',
        'tier',
        'wealth_score',
        'completeness_score',
        'metadata',
    ];

    public const TIERS = [
        'standard'       => 'Standard',
        'premium'        => 'Premium',
        'high_net_worth' => 'High Net Worth',
        'vip'            => 'VIP',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(ClientPhoneNumber::class);
    }

    public function primaryPhone(): HasOne
    {
        return $this->hasOne(ClientPhoneNumber::class)->where('is_primary', true);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ClientEmail::class);
    }

    public function primaryEmail(): HasOne
    {
        return $this->hasOne(ClientEmail::class)->where('is_primary', true);
    }

    public function getPrimaryEmailAddressAttribute(): ?string
    {
        return $this->primaryEmail?->email;
    }

    public function setPrimaryEmailAddress(?string $email): void
    {
        $email = trim((string) $email);

        if ($email === '') {
            $this->emails()->where('is_primary', true)->delete();
            $this->unsetRelation('primaryEmail');

            return;
        }

        $existing = $this->emails()
            ->whereRaw('lower(email) = lower(?)', [$email])
            ->first();

        $this->emails()
            ->where('is_primary', true)
            ->when($existing, fn ($q) => $q->where('id', '<>', $existing->id))
            ->update(['is_primary' => false]);

        if ($existing) {
            $existing->forceFill(['email' => $email, 'is_primary' => true])->save();
        } else {
            $this->emails()->create(['email' => $email, 'is_primary' => true]);
        }

        $this->unsetRelation('primaryEmail');
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(Ownership::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ClientSource::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class)->latest('created_at');
    }

    public function activityTimeline(): HasMany
    {
        return $this->hasMany(ClientActivity::class)->latest('activity_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_tags')->withTimestamps();
    }

    public function scopeForEmirate(\Illuminate\Database\Eloquent\Builder $query, string $emirate): void
    {
        $query->where('emirate', $emirate);
    }
}
