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
        'manually_dead',
    ];

    protected function casts(): array
    {
        return [
            'last_messaged_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'manually_dead' => 'boolean',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class);
    }
}
