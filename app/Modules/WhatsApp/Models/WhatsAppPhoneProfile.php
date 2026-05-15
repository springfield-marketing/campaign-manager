<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppPhoneProfile extends Model
{
    protected $table = 'whatsapp_phone_profiles';

    protected $fillable = [
        'client_phone_number_id',
        'consecutive_hard_fail_count',
        'last_message_status',
        'last_failure_reason',
        'last_messaged_at',
        'usage_status',
        'cooldown_until',
    ];

    protected function casts(): array
    {
        return [
            'last_messaged_at' => 'datetime',
            'cooldown_until'   => 'datetime',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class);
    }

    public function isActive(): bool
    {
        return $this->usage_status === 'active';
    }

    public function isDead(): bool
    {
        return $this->usage_status === 'dead';
    }

    public function isOnCooldown(): bool
    {
        return $this->usage_status === 'cooldown'
            && $this->cooldown_until !== null
            && $this->cooldown_until->isFuture();
    }
}
