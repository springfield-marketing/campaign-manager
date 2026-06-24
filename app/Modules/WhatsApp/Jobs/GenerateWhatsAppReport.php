<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppReport;
use App\Modules\WhatsApp\Support\WhatsAppFatigueReportGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class GenerateWhatsAppReport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $reportId,
    ) {
    }

    public function handle(WhatsAppFatigueReportGenerator $fatigue): void
    {
        $report = WhatsAppReport::query()->findOrFail($this->reportId);

        match ($report->type) {
            WhatsAppReport::TYPE_FATIGUE => $fatigue->generate($report),
            default => throw new RuntimeException("Unknown report type: {$report->type}"),
        };
    }
}
