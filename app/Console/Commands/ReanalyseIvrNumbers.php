<?php

namespace App\Console\Commands;

use App\Models\ClientPhoneNumber;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReanalyseIvrNumbers extends Command
{
    protected $signature = 'ivr:reanalyse-numbers
                            {--chunk=500 : Number of numbers to process per batch}
                            {--stale-only : Only reprocess numbers whose cooldown_until gap does not match current settings}';

    protected $description = 'Re-run the IVR number eligibility check, rebuilding usage_status and cooldown_until from the current settings.';

    public function handle(NumberEligibilityService $service): int
    {
        $staleOnly = $this->option('stale-only');

        if ($staleOnly) {
            $total = DB::table('ivr_phone_profiles')
                ->whereNotNull('cooldown_until')
                ->whereNotNull('last_called_at')
                ->whereRaw("ROUND(EXTRACT(EPOCH FROM (cooldown_until - last_called_at)) / 86400) NOT IN (?, ?)", [14, 1])
                ->count();
        } else {
            $total = DB::table('ivr_phone_profiles')->count();
        }

        if ($total === 0) {
            $this->info('No IVR numbers to process.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} numbers…");
        $bar    = $this->output->createProgressBar($total);
        $bar->start();

        $chunk  = (int) $this->option('chunk');
        $done   = 0;
        $lastId = 0;

        do {
            $query = DB::table('ivr_phone_profiles')
                ->where('client_phone_number_id', '>', $lastId)
                ->orderBy('client_phone_number_id')
                ->limit($chunk);

            if ($staleOnly) {
                $query->whereNotNull('cooldown_until')
                    ->whereNotNull('last_called_at')
                    ->whereRaw("ROUND(EXTRACT(EPOCH FROM (cooldown_until - last_called_at)) / 86400) NOT IN (?, ?)", [14, 1]);
            }

            $ids = $query->pluck('client_phone_number_id');

            foreach ($ids as $id) {
                $number = ClientPhoneNumber::find($id);
                if ($number) {
                    $service->refresh($number);
                }
                $bar->advance();
                $done++;
                $lastId = $id;
            }
        } while ($ids->count() === $chunk);

        $bar->finish();
        $this->newLine();
        $this->info("Done. Processed {$done} numbers.");

        return self::SUCCESS;
    }
}
