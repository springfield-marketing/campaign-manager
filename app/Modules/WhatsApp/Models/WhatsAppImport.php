<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
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
        'uploaded_by',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'duplicate_rows',
        'error_message',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusMessage(): string
    {
        return match ($this->status) {
            WhatsAppImportStatus::Pending->value => 'Waiting for the queue worker to start.',
            WhatsAppImportStatus::Processing->value => 'Import is running in the background.',
            WhatsAppImportStatus::Completed->value => 'Import completed successfully.',
            WhatsAppImportStatus::CompletedWithErrors->value => 'Import completed with some row errors.',
            WhatsAppImportStatus::Reverting->value => 'Revert is running in the background.',
            WhatsAppImportStatus::Reverted->value => 'Revert complete'.($this->reverted_at ? ' on '.$this->reverted_at->format('M j, Y g:i A') : '').'.',
            WhatsAppImportStatus::RevertFailed->value => 'Revert failed'.($this->error_message ? ': '.$this->error_message : '.'),
            WhatsAppImportStatus::Failed->value => 'Import failed'.($this->error_message ? ': '.$this->error_message : '.'),
            default => ucfirst($this->statusLabel()),
        };
    }
}
