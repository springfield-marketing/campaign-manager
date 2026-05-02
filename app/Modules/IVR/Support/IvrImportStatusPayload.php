<?php

namespace App\Modules\IVR\Support;

use App\Modules\IVR\Models\IvrImport;

class IvrImportStatusPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function make(IvrImport $import): array
    {
        $deleteProgress = $import->deleteProgress();
        $isDeleting = in_array($import->status, ['deleting', 'deleted', 'delete_failed'], true);
        $progress = $isDeleting ? $deleteProgress['percent'] : ($import->total_rows > 0
            ? min(100, round(($import->processed_rows / $import->total_rows) * 100))
            : 0);

        return [
            'id' => $import->id,
            'type' => $import->type,
            'status' => $import->status,
            'status_label' => $import->statusLabel(),
            'status_message' => $import->statusMessage(),
            'original_file_name' => $import->original_file_name,
            'source_name' => $import->source_name,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->failed_rows,
            'duplicate_rows' => $import->duplicate_rows,
            'progress' => $progress,
            'delete_progress' => $deleteProgress,
            'progress_label' => self::progressLabel($import, $isDeleting, $deleteProgress),
            'detail_label' => self::detailLabel($import, $isDeleting, $deleteProgress),
            'is_active' => in_array($import->status, ['pending', 'processing', 'deleting', 'reverting'], true),
        ];
    }

    /**
     * @param  array<string, int|string|null>  $deleteProgress
     */
    private static function progressLabel(IvrImport $import, bool $isDeleting, array $deleteProgress): string
    {
        if ($isDeleting) {
            return "{$deleteProgress['processed']} / {$deleteProgress['total']} delete steps";
        }

        return "{$import->processed_rows} / ".($import->total_rows ?: '-');
    }

    /**
     * @param  array<string, int|string|null>  $deleteProgress
     */
    private static function detailLabel(IvrImport $import, bool $isDeleting, array $deleteProgress): string
    {
        if ($isDeleting) {
            return "{$deleteProgress['source_rows_deleted']} source links deleted - {$deleteProgress['phone_numbers_deleted']} phone numbers deleted - {$deleteProgress['clients_deleted']} clients deleted";
        }

        return match ($import->type) {
            'unsubscribers' => "{$import->successful_rows} added - {$import->duplicate_rows} already existed - {$import->failed_rows} failed",
            default => "{$import->successful_rows} imported - {$import->failed_rows} failed - {$import->duplicate_rows} duplicates",
        };
    }
}
