<?php

namespace App\Modules\IVR\Jobs;

use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\CampaignResultsProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIvrCampaignResultsImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $importId,
    ) {
    }

    public function handle(CampaignResultsProcessor $processor): void
    {
        $import = IvrImport::query()->findOrFail($this->importId);

        $processor->process($import);
    }
}
