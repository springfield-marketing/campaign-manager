<?php

namespace App\Console\Commands;

use App\Modules\IVR\Support\IvrSummaryService;
use Illuminate\Console\Command;

class RefreshIvrSummaries extends Command
{
    protected $signature = 'ivr:refresh-summaries
                            {--year= : Recompute only this year}
                            {--month= : Recompute only this month (requires --year)}';

    protected $description = 'Recompute IVR monthly summary aggregates from call records';

    public function handle(IvrSummaryService $service): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;

        if ($year && $month) {
            $this->info("Recomputing {$year}-{$month}…");
            $service->recompute($year, $month);
            $this->info('Done.');

            return self::SUCCESS;
        }

        if ($year) {
            $this->info("Recomputing all months in {$year}…");
            for ($m = 1; $m <= 12; $m++) {
                $service->recompute($year, $m);
            }
            $this->info('Done.');

            return self::SUCCESS;
        }

        $this->info('Recomputing all months with data…');
        $service->recomputeAllMonths();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
