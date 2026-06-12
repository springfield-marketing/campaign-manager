<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WhatsAppExportBatch extends Model
{
    protected $table = 'whatsapp_export_batches';

    protected $fillable = [
        'name',
        'exported_by',
        'record_count',
        'filters_summary',
    ];

    protected function casts(): array
    {
        return [
            'filters_summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public function phoneNumbers(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientPhoneNumber::class,
            'whatsapp_export_batch_numbers',
            'whatsapp_export_batch_id',
            'client_phone_number_id',
        );
    }
}
