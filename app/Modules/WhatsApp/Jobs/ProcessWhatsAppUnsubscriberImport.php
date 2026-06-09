<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppUnsubscriberImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppUnsubscriberImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries   = 1;
    public bool $failOnTimeout = true;

    public function __construct(private readonly int $importId) {}

    public function handle(WhatsAppUnsubscriberImportProcessor $processor): void
    {
        $import = WhatsAppImport::findOrFail($this->importId);
        $processor->process($import);
    }

    public function failed(Throwable $exception): void
    {
        WhatsAppImport::query()
            ->whereKey($this->importId)
            ->where('status', WhatsAppImportStatus::Processing->value)
            ->update([
                'status'        => WhatsAppImportStatus::Failed,
                'error_message' => 'Import timed out or failed: '.$exception->getMessage(),
                'completed_at'  => now(),
            ]);
    }
}
