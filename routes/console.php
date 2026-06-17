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
