<?php

namespace App\Console\Commands;

use App\Modules\IVR\Models\IvrSettings;
use App\Modules\IVR\Support\IvrBatchEligibilityUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;

class ReanalyseIvrNumbers extends Command
{
    protected $signature = 'ivr:reanalyse-numbers
                            {--chunk=500 : Number of numbers to process per batch}
                            {--stale-only : Only reprocess numbers whose cooldown gap does not match current settings}';

    protected $description = 'Re-run the IVR number eligibility check, rebuilding usage_status and cooldown_until from the current settings.';

    public function handle(IvrBatchEligibilityUpdater $updater): int
    {
        DB::disableQueryLog();

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $staleOnly = $this->option('stale-only');
        $settings  = IvrSettings::current();

        if ($staleOnly) {
            $answeredDays = (int) $settings->cooldown_answered_days;
            $missedDays   = (int) $settings->cooldown_missed_days;

            $total = DB::table('ivr_phone_profiles')
                ->whereNotNull('cooldown_until')
                ->whereNotNull('last_called_at')
                ->whereRaw(
                    'ROUND(EXTRACT(EPOCH FROM (cooldown_until - last_called_at)) / 86400) NOT IN (?, ?)',
                    [$answeredDays, $missedDays],
                )
                ->count();
        } else {
            $total = DB::table('ivr_call_records')
                ->whereNotNull('client_phone_number_id')
                ->distinct('client_phone_number_id')
                ->count('client_phone_number_id');
        }

        if ($total === 0) {
            $this->info('No IVR numbers to process.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} numbers…");
        $bar   = $this->output->createProgressBar($total);
        $bar->start();

        $chunk  = (int) $this->option('chunk');
        $done   = 0;
        $lastId = 0;

        do {
            if ($staleOnly) {
                $ids = DB::table('ivr_phone_profiles')
                    ->where('client_phone_number_id', '>', $lastId)
                    ->whereNotNull('cooldown_until')
                    ->whereNotNull('last_called_at')
                    ->whereRaw(
                        'ROUND(EXTRACT(EPOCH FROM (cooldown_until - last_called_at)) / 86400) NOT IN (?, ?)',
                        [$answeredDays, $missedDays],
                    )
                    ->orderBy('client_phone_number_id')
                    ->limit($chunk)
                    ->pluck('client_phone_number_id');
            } else {
                $ids = DB::table('ivr_call_records')
                    ->whereNotNull('client_phone_number_id')
                    ->where('client_phone_number_id', '>', $lastId)
                    ->groupBy('client_phone_number_id')
                    ->orderBy('client_phone_number_id')
                    ->limit($chunk)
                    ->pluck('client_phone_number_id');
            }

            if ($ids->isNotEmpty()) {
                $updater->run($ids->all());
                $done  += $ids->count();
                $lastId = $ids->last();
                $bar->advance($ids->count());
            }
        } while ($ids->count() === $chunk);

        $bar->finish();
        $this->newLine();
        $this->info("Done. Processed {$done} numbers.");

        if (class_exists(Telescope::class)) {
            Telescope::startRecording();
        }

        return self::SUCCESS;
    }
}
