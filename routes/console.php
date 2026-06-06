<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup at 02:00; keeps the last BACKUP_KEEP_DAYS days
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();

// Horizon job-throughput snapshots (powers the metrics graphs in /horizon)
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Prune Telescope entries older than 48 hours so the table stays manageable
Schedule::command('telescope:prune --hours=48')->daily();
