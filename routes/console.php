<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup at 02:00; keeps the last BACKUP_KEEP_DAYS days
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();

// Weekly data-quality audit — surfaces bad client merges (incl. IMP-001 stub-name
// multi-number clients). Output is logged so the run can be reviewed after the fact.
Schedule::command('clients:audit-data-quality')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/data-quality-audit.log'));

// Daily IVR budget check — raises an admin notification when this month's spend is
// projected (current burn rate × working days) to exceed the monthly minute quota.
Schedule::command('ivr:check-budget')
    ->dailyAt('08:00')
    ->withoutOverlapping();

// Daily prune of WhatsApp export-batch history older than 7 days. Batches only exist to dedupe
// back-to-back exports (so a second 10k export excludes the first 10k); after a week they're no
// longer needed, and pruning keeps whatsapp_export_batch_numbers from growing forever.
Schedule::command('whatsapp:prune-export-batches')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Daily prune of the activity log, keeping the last 12 months so it can't grow without bound.
Schedule::command('activity-log:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Keep the IVR & WhatsApp number stat cards warm. Their aggregates take ~8-11s over ~1M rows,
// so we recompute them on a schedule and cache the result — a real user never waits for a cold
// render. Every 2 min keeps the ~19s/run aggregate off the DB most of the time while staying
// comfortably inside the 5-min cache TTL, so the keys never expire between runs.
Schedule::command('stats:warm-number-widgets')
    ->everyTwoMinutes()
    ->withoutOverlapping();
