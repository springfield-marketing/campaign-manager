<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    protected $signature = 'activity-log:prune
                            {--months=12 : Delete activity older than this many months}';

    protected $description = 'Delete activity-log entries older than the retention window';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonths($months);

        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} activity-log entr(ies) older than {$months} month(s).");

        return self::SUCCESS;
    }
}
