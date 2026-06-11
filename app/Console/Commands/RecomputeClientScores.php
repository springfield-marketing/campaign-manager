<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeClientScoresJob;
use App\Support\ClientScoringService;
use Illuminate\Console\Command;

class RecomputeClientScores extends Command
{
    protected $signature = 'clients:rescore
                            {--queue : Dispatch as a background job instead of running inline}
                            {--ids= : Comma-separated client IDs to limit rescoring}';

    protected $description = 'Recompute wealth_score, completeness_score and tier for all (or specified) clients';

    public function handle(ClientScoringService $service): int
    {
        $ids = $this->option('ids')
            ? array_map('intval', explode(',', $this->option('ids')))
            : [];

        if ($this->option('queue')) {
            RecomputeClientScoresJob::dispatch($ids)->onQueue('analysis');
            $scope = $ids ? count($ids) . ' client(s)' : 'all clients';
            $this->info("Queued rescore job for {$scope} on the [analysis] queue.");
            return self::SUCCESS;
        }

        if ($ids) {
            $this->info('Rescoring ' . count($ids) . ' client(s)…');
            $service->recomputeBulk($ids);
        } else {
            $total = \App\Models\Client::count();
            $this->info("Rescoring all {$total} clients (inline)…");
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            \App\Models\Client::query()->select('id')->orderBy('id')->chunk(500, function ($clients) use ($service, $bar): void {
                $service->recomputeBulk($clients->pluck('id')->all());
                $bar->advance($clients->count());
            });

            $bar->finish();
            $this->newLine();
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
