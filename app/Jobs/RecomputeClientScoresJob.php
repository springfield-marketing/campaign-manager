<?php

namespace App\Jobs;

use App\Support\ClientScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecomputeClientScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    /** @param int[] $clientIds */
    public function __construct(public readonly array $clientIds = []) {}

    public function handle(ClientScoringService $service): void
    {
        if (empty($this->clientIds)) {
            $service->recomputeAll();
        } else {
            $service->recomputeBulk($this->clientIds);
        }
    }
}
