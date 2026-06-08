<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessRawIvrImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200; // 2 hours — large CSVs (250k+ rows) can take over 30 min

    public int $tries = 1; // retrying a partially-complete import just adds duplicate work

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $importId,
    ) {
    }

    public function handle(RawImportProcessor $processor): void
    {
        $import = IvrImport::query()->findOrFail($this->importId);

        $processor->process($import);
    }

    public function failed(Throwable $exception): void
    {
        IvrImport::query()
            ->whereKey($this->importId)
            ->where('status', IvrImportStatus::Processing->value)
            ->update([
                'status'        => IvrImportStatus::Failed,
                'error_message' => 'Import timed out. Rows processed before the timeout were saved.',
                'completed_at'  => now(),
            ]);
    }
}
