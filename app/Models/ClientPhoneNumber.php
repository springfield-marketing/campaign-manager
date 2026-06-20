<?php

namespace App\Models;

use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrPhoneProfile;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppPhoneProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClientPhoneNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'raw_phone',
        'normalized_phone',
        'country_code',
        'national_number',
        'label',
        'detected_country',
        'is_uae',
        'is_primary',
        'is_whatsapp',
        'is_ivr',
        'is_whatsapp_lead',
        'verification_status',
        'priority',
        'last_source_name',
        'last_imported_at',
        'unsubscribed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_uae' => 'boolean',
            'is_primary' => 'boolean',
            'is_whatsapp' => 'boolean',
            'is_ivr' => 'boolean',
            'is_whatsapp_lead' => 'boolean',
            'priority' => 'integer',
            'last_imported_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'reentered_while_suppressed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ClientSource::class);
    }

    public function firstSource(): HasOne
    {
        return $this->hasOne(ClientSource::class)->oldestOfMany();
    }

    public function suppressions(): HasMany
    {
        return $this->hasMany(ContactSuppression::class);
    }

    public function ivrCallRecords(): HasMany
    {
        return $this->hasMany(IvrCallRecord::class);
    }

    public function whatsAppMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function ivrProfile(): HasOne
    {
        return $this->hasOne(IvrPhoneProfile::class);
    }

    public function whatsAppProfile(): HasOne
    {
        return $this->hasOne(WhatsAppPhoneProfile::class);
    }

    public function effectiveCallingStatus(): string
    {
        // Use the pre-loaded virtual attribute from withExists() when available
        $isSuppressed = array_key_exists('is_ivr_suppressed', $this->getAttributes())
            ? (bool) $this->getAttribute('is_ivr_suppressed')
            : $this->suppressions()->activeIvr()->exists();

        if ($this->unsubscribed_at !== null || $isSuppressed) {
            return 'dead';
        }

        $profile = $this->ivrProfile;

        if (! $profile) {
            return 'active';
        }

        if ($profile->usage_status === 'dead') {
            return 'dead';
        }

        if ($profile->cooldown_until && now()->lt($profile->cooldown_until)) {
            return 'inactive';
        }

        return $profile->usage_status ?: 'active';
    }

    public function scopeReadyToCall(Builder $query): Builder
    {
        return $query
            ->whereNull('unsubscribed_at')
            ->whereDoesntHave('suppressions', fn (Builder $q) => $q->activeIvr())
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('ivrProfile')
                ->orWhereHas('ivrProfile', fn (Builder $profile) => $profile
                    ->where('usage_status', 'active')
                    ->where(fn (Builder $cooldown) => $cooldown
                        ->whereNull('cooldown_until')
                        ->orWhere('cooldown_until', '<=', now())
                    )
                )
            );
    }

    public function scopeResting(Builder $query): Builder
    {
        return $query
            ->whereNull('unsubscribed_at')
            ->whereDoesntHave('suppressions', fn (Builder $q) => $q->activeIvr())
            ->whereHas('ivrProfile', fn (Builder $profile) => $profile
                ->where(fn (Builder $q) => $q
                    ->where('usage_status', 'inactive')
                    ->orWhere(fn (Builder $cooldown) => $cooldown
                        ->whereNotNull('cooldown_until')
                        ->where('cooldown_until', '>', now())
                    )
                )
            );
    }

    public function scopeNotCallable(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNotNull('unsubscribed_at')
            ->orWhereHas('suppressions', fn (Builder $s) => $s->activeIvr())
            ->orWhereHas('ivrProfile', fn (Builder $profile) => $profile
                ->where('usage_status', 'dead')
            )
        );
    }
}
