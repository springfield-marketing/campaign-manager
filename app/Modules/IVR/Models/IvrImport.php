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
}
