<?php

namespace App\Console\Commands;

use App\Filament\Widgets\IvrNumberStatsWidget;
use App\Filament\Widgets\WhatsAppNumberStatsWidget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmNumberStatsCache extends Command
{
    protected $signature = 'stats:warm-number-widgets';

    protected $description = 'Pre-compute the IVR & WhatsApp number stat cards so the widgets never block a page load.';

    public function handle(): int
    {
        // The aggregates take ~8-11s over ~1M rows; computing them here on a schedule means a
        // real user never waits for a cold render. The widgets read these same cache keys.
        Cache::put(IvrNumberStatsWidget::CACHE_KEY, IvrNumberStatsWidget::globalTotals(), IvrNumberStatsWidget::CACHE_TTL);
        Cache::put(WhatsAppNumberStatsWidget::CACHE_KEY, WhatsAppNumberStatsWidget::globalTotals(), WhatsAppNumberStatsWidget::CACHE_TTL);

        $this->info('Number stat caches warmed.');

        return self::SUCCESS;
    }
}
