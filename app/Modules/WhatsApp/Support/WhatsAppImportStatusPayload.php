<?php

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Models\WhatsAppImport;

class WhatsAppImportStatusPayload
{
    /** @return array<string, mixed> */
    public static function make(WhatsAppImport $import): array
    {
        $progress = $import->total_rows > 0
            ? min(100, round(($import->processed_rows / $import->total_rows) * 100))
            : 0;

        $isActive = in_array($import->status, [
            WhatsAppImportStatus::Pending->value,
            WhatsAppImportStatus::Processing->value,
        ], true);

        return [
            'id'                 => $import->id,
            'type'               => $import->type,
            'status'             => $import->status,
            'status_label'       => $import->statusLabel(),
            'status_message'     => $import->statusMessage(),
            'original_file_name' => $import->original_file_name,
            'source_name'        => $import->source_name,
            'total_rows'         => $import->total_rows,
            'processed_rows'     => $import->processed_rows,
            'successful_rows'    => $import->successful_rows,
            'failed_rows'        => $import->failed_rows,
            'duplicate_rows'     => $import->duplicate_rows,
            'progress'           => $progress,
            'progress_label'     => "{$import->processed_rows} / " . ($import->total_rows ?: '-'),
            'detail_label'       => "{$import->successful_rows} imported - {$import->failed_rows} failed - {$import->duplicate_rows} duplicates",
            'is_active'          => $isActive,
        ];
    }
}
