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
        'consecutive_failed_count',
        'last_message_status',
        'last_messaged_at',
    ];

    protected function casts(): array
    {
        return [
            'last_messaged_at' => 'datetime',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class);
    }
}
