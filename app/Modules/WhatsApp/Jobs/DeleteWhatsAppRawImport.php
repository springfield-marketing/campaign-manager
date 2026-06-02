<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppRawImportDeleter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeleteWhatsAppRawImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $importId,
        public readonly ?int $userId = null,
        public readonly ?string $reason = null,
    ) {
    }

    public function handle(WhatsAppRawImportDeleter $deleter): void
    {
        $import = WhatsAppImport::query()->findOrFail($this->importId);

        $deleter->delete($import, $this->userId, $this->reason);
    }

    public function failed(Throwable $exception): void
    {
        WhatsAppImport::query()
            ->whereKey($this->importId)
            ->whereNull('reverted_at')
            ->update([
                'status'        => WhatsAppImportStatus::DeleteFailed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
