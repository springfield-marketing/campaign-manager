<?php

namespace App\Modules\IVR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentralDatabaseExport extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'file_name',
        'storage_path',
        'requested_by',
        'total_rows',
        'processed_rows',
        'file_size',
        'summary',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function progressPercent(): int
    {
        if ($this->total_rows === 0) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return min(100, (int) floor(($this->processed_rows / $this->total_rows) * 100));
    }
}
