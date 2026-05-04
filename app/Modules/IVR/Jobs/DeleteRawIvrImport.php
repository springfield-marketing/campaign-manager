<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportDeleter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeleteRawIvrImport implements ShouldQueue
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

    public function handle(RawImportDeleter $deleter): void
    {
        $import = IvrImport::query()->findOrFail($this->importId);

        $deleter->delete($import, $this->userId, $this->reason);
    }

    public function failed(Throwable $exception): void
    {
        IvrImport::query()
            ->whereKey($this->importId)
            ->whereNull('reverted_at')
            ->update([
                'status' => IvrImportStatus::DeleteFailed,
                'error_message' => $exception->getMessage(),
            ]);
    }
}
