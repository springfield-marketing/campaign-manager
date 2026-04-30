<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'client_phone_number_id',
        'channel',
        'source_type',
        'source_name',
        'source_file_name',
        'source_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(ClientPhoneNumber::class, 'client_phone_number_id');
    }
}
