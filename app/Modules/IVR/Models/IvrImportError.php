<?php

namespace App\Modules\IVR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrImportError extends Model
{
    protected $fillable = [
        'ivr_import_id',
        'row_number',
        'error_type',
        'error_message',
        'row_payload',
    ];

    protected function casts(): array
    {
        return [
            'row_payload' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(IvrImport::class, 'ivr_import_id');
    }
}
