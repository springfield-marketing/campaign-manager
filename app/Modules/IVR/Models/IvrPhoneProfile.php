<?php

namespace App\Modules\IVR\Models;

use App\Models\ClientPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrPhoneProfile extends Model
{
    protected $table = 'ivr_phone_profiles';

    protected $fillable = [
        'client_phone_number_id',
        'usage_status',
        'last_call_outcome',
        'last_called_at',
        'cooldown_until',
    ];

    protected function casts(): array
    {
        return [
            'last_called_at' => 'datetime',
            'cooldown_until' => 'datetime',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class);
    }
}
