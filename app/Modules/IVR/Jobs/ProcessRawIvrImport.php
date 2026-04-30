<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\RawImportProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRawIvrImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $importId,
    ) {
    }

    public function handle(RawImportProcessor $processor): void
    {
        $import = IvrImport::query()->findOrFail($this->importId);

        $processor->process($import);
    }
}
