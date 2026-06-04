<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Modules\WhatsApp\Support\WhatsAppCampaignResultsProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppCampaignResultsImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $importId,
    ) {}

    public function handle(WhatsAppCampaignResultsProcessor $processor): void
    {
        $import = WhatsAppImport::query()->findOrFail($this->importId);

        $processor->process($import);
    }

    public function failed(Throwable $exception): void
    {
        WhatsAppImport::query()
            ->whereKey($this->importId)
            ->where('status', WhatsAppImportStatus::Processing->value)
            ->update([
                'status'        => WhatsAppImportStatus::Failed,
                'error_message' => 'Import timed out. Rows processed before the timeout were saved.',
                'completed_at'  => now(),
            ]);
    }
}
