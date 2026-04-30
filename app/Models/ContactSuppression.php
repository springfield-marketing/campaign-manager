<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSuppression extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_phone_number_id',
        'channel',
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
}
