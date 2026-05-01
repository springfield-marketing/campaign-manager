<?php

namespace App\Modules\IVR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrImport extends Model
{
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

    public function errors(): HasMany
    {
        return $this->hasMany(IvrImportError::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function callRecords(): HasMany
    {
        return $this->hasMany(IvrCallRecord::class);
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusMessage(): string
    {
        return match ($this->status) {
            'pending' => 'Waiting for the queue worker to start.',
            'processing' => 'Import is running in the background.',
            'completed' => 'Import completed successfully.',
            'reverting' => 'Revert is running in the background. This can take a few minutes for large files.',
            'reverted' => 'Revert complete'.($this->reverted_at ? ' on '.$this->reverted_at->format('M j, Y g:i A') : '').'.',
            'revert_failed' => 'Revert failed'.($this->error_message ? ': '.$this->error_message : '.'),
            'failed' => 'Import failed'.($this->error_message ? ': '.$this->error_message : '.'),
            default => ucfirst($this->statusLabel()),
        };
    }
}
