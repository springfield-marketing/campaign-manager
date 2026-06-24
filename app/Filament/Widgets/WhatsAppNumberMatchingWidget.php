<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WhatsAppNumbers\Pages\ListWhatsAppNumbers;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shows how many numbers match the filters currently applied to the WhatsApp Numbers table.
 *
 * The list page uses Simple pagination (no COUNT over ~1M rows on every load), so the pager
 * doesn't show a total. This single, lazy count fills that gap. It's always a count over a
 * filtered subset — the table defaults to the "active" status filter, so it's never an
 * unguarded COUNT(*) of the whole table.
 */
class WhatsAppNumberMatchingWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    // Load after the page shell so the count never blocks the initial render.
    protected static bool $isLazy = true;

    protected function getTablePage(): string
    {
        return ListWhatsAppNumbers::class;
    }

    protected function getStats(): array
    {
        $count = $this->getPageTableQuery()->count();

        return [
            Stat::make('Matching filters', number_format($count))
                ->icon('heroicon-o-funnel')
                ->color('primary')
                ->description('Numbers matching the filters currently applied to the table'),
        ];
    }
}
