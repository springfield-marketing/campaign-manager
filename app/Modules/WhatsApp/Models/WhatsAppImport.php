<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppImport extends Model
{
    protected $table = 'whatsapp_imports';

    protected $fillable = [
        'type',
        'status',
        'original_file_name',
        'stored_file_name',
        'storage_path',
        'source_name',
        'uploaded_by',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'duplicate_rows',
        'error_message',
        'column_mapping',
        'lenient_phones',
        'summary',
        'started_at',
        'completed_at',
        'reverted_at',
        'reverted_by',
        'revert_reason',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'lenient_phones' => 'boolean',
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_import_id');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(WhatsAppImportError::class, 'whatsapp_import_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function platform(): ?WhatsAppPlatform
    {
        return WhatsAppPlatform::tryFrom($this->source_name ?? '');
    }
}
