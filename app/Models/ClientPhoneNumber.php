<?php

namespace App\Models;

use App\Modules\IVR\Models\IvrCallRecord;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'verification_status',
        'priority',
        'usage_status',
        'last_call_outcome',
        'last_source_name',
        'last_imported_at',
        'last_called_at',
        'cooldown_until',
        'unsubscribed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_uae' => 'boolean',
            'is_primary' => 'boolean',
            'is_whatsapp' => 'boolean',
            'priority' => 'integer',
            'last_imported_at' => 'datetime',
            'last_called_at' => 'datetime',
            'cooldown_until' => 'datetime',
            'unsubscribed_at' => 'datetime',
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

    public function suppressions(): HasMany
    {
        return $this->hasMany(ContactSuppression::class);
    }

    public function ivrCallRecords(): HasMany
    {
        return $this->hasMany(IvrCallRecord::class);
    }
}
