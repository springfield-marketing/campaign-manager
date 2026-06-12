<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppSettings;
use App\Modules\WhatsApp\Support\WhatsAppBatchProfileUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BatchAnalyseWhatsAppNumbers implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries   = 3;

    /**
     * @param array<int> $phoneNumberIds  Empty = process every number in the database.
     * @param bool       $trackProgress   Write running/completed status to whatsapp_settings.
     */
    public function __construct(
        public readonly array $phoneNumberIds = [],
        public readonly bool  $trackProgress  = false,
    ) {}

    public function handle(WhatsAppBatchProfileUpdater $updater): void
    {
        if ($this->trackProgress) {
            WhatsAppSettings::where('lock_key', 'default')->update([
                'reanalysis_status'       => 'running',
                'reanalysis_started_at'   => now(),
                'reanalysis_completed_at' => null,
            ]);
        }

        $updater->run($this->phoneNumberIds);

        if ($this->trackProgress) {
            WhatsAppSettings::where('lock_key', 'default')->update([
                'reanalysis_status'       => 'completed',
                'reanalysis_completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->trackProgress) {
            WhatsAppSettings::where('lock_key', 'default')->update([
                'reanalysis_status'       => 'failed',
                'reanalysis_completed_at' => now(),
            ]);
        }
    }
}
