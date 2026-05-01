<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportReverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RevertRawIvrImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $importId,
        public readonly ?int $userId = null,
        public readonly ?string $reason = null,
    ) {
    }

    public function handle(RawImportReverter $reverter): void
    {
        $import = IvrImport::query()->findOrFail($this->importId);

        $reverter->revert($import, $this->userId, $this->reason);
    }
}
