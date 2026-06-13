<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSuppression extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_phone_number_id',
        'channel',
        'platform',
        'reason',
        'context',
        'suppressed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'suppressed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class, 'client_phone_number_id');
    }

    public function scopeActiveIvr(Builder $query): Builder
    {
        return $query
            ->whereNull('released_at')
            ->where(fn (Builder $q) => $q->whereNull('channel')->orWhere('channel', 'ivr'));
    }

    public function scopeActiveWhatsApp(Builder $query, ?string $platform = null): Builder
    {
        return $query
            ->whereNull('released_at')
            ->where('channel', 'whatsapp')
            ->when(
                $platform !== null,
                fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner->whereNull('platform')->orWhere('platform', $platform)
                )
            );
    }
}
