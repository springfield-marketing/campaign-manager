<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
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
            'column_mapping'  => 'array',
            'lenient_phones'  => 'boolean',
            'summary'         => 'array',
            'started_at'      => 'datetime',
            'completed_at'    => 'datetime',
            'reverted_at'     => 'datetime',
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

    public function broadcastProgress(): void
    {
        // HTTP polling is used instead of WebSocket for WhatsApp imports
    }

    public function deleteProgress(): array
    {
        $default = [
            'stage' => null,
            'stage_label' => null,
            'processed' => 0,
            'total' => 7,
            'percent' => 0,
            'source_rows_deleted' => 0,
            'phone_numbers_deleted' => 0,
            'clients_deleted' => 0,
        ];

        return array_merge($default, ($this->summary ?? [])['delete_progress'] ?? []);
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusMessage(): string
    {
        return match ($this->status) {
            WhatsAppImportStatus::Draft->value => 'Column mapping not confirmed — setup not complete.',
            WhatsAppImportStatus::Pending->value => 'Waiting for the queue worker to start.',
            WhatsAppImportStatus::Processing->value => 'Import is running in the background.',
            WhatsAppImportStatus::Completed->value => 'Import completed successfully.',
            WhatsAppImportStatus::CompletedWithErrors->value => 'Import completed with some row errors.',
            WhatsAppImportStatus::Deleting->value => 'Deleting contacts and source links in the background.',
            WhatsAppImportStatus::Deleted->value => 'Import deleted'.($this->reverted_at ? ' on '.$this->reverted_at->format('M j, Y g:i A') : '').'.',
            WhatsAppImportStatus::DeleteFailed->value => 'Delete failed'.($this->error_message ? ': '.$this->error_message : '.'),
            WhatsAppImportStatus::Reverting->value => 'Revert is running in the background.',
            WhatsAppImportStatus::Reverted->value => 'Revert complete'.($this->reverted_at ? ' on '.$this->reverted_at->format('M j, Y g:i A') : '').'.',
            WhatsAppImportStatus::RevertFailed->value => 'Revert failed'.($this->error_message ? ': '.$this->error_message : '.'),
            WhatsAppImportStatus::Failed->value => 'Import failed'.($this->error_message ? ': '.$this->error_message : '.'),
            default => ucfirst($this->statusLabel()),
        };
    }
}
