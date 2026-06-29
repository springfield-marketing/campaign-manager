<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Shows how many numbers match the filters currently applied to the IVR Numbers table.
 *
 * The list page uses Simple pagination + deferred loading (no COUNT over ~1M rows on every
 * load), so the pager never shows a total. This single, lazy count fills that gap so you can
 * see "how many are available" after filtering without opening the export modal.
 *
 * Unlike WhatsApp, the IVR table has no default status filter, so with no filters applied this
 * counts the full UAE-mobile base — hence the lazy load, which keeps it off the initial render.
 */
class IvrNumberMatchingWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected static bool $isDiscovered = false;

    // Load after the page shell so the count never blocks the initial render.
    protected static bool $isLazy = true;

    protected function getTablePage(): string
    {
        return ListIvrNumbers::class;
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
