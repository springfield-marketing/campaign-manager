<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppImportError extends Model
{
    protected $table = 'whatsapp_import_errors';

    protected $fillable = [
        'whatsapp_import_id',
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
        return $this->belongsTo(WhatsAppImport::class, 'whatsapp_import_id');
    }
}
