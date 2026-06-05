<?php

namespace App\Modules\WhatsApp\Jobs;

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
     */
    public function __construct(public readonly array $phoneNumberIds = []) {}

    public function handle(WhatsAppBatchProfileUpdater $updater): void
    {
        $updater->run($this->phoneNumberIds);
    }
}
