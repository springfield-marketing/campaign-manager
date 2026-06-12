<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Support\IvrReportData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class IvrStatsWidget extends StatsOverviewWidget
{
    public int $year;
    public ?int $month;

    protected static bool $isLazy = false;

    public function mount(int $year = 0, ?int $month = null): void
    {
        $this->year  = $year ?: now()->year;
        $this->month = $month ?? now()->month;
    }

    #[On('ivr-filter-changed')]
    public function onFilterChanged(int $year, ?int $month): void
    {
        $this->year  = $year;
        $this->month = $month;
    }

    protected function getStats(): array
    {
        $data = app(IvrReportData::class)->forPeriod($this->year, $this->month);
        $s    = $data['summary'];

        $didNotProcess = $s['total_calls'] - $s['answered_calls'] - $s['missed_calls'];

        return [
            Stat::make('Total Calls', number_format($s['total_calls']))
                ->icon('heroicon-o-phone')
                ->extraAttributes(['x-tooltip.raw' => 'Total call attempts in the selected period — includes answered, missed, and calls that were filtered before dialing.']),

            Stat::make('Answered', number_format($s['answered_calls']))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($s['total_calls'] > 0
                    ? number_format($s['answered_calls'] / $s['total_calls'] * 100, 1) . '% answer rate'
                    : null
                )
                ->extraAttributes(['x-tooltip.raw' => 'Calls where the recipient picked up. Answer rate is answered ÷ total calls.']),

            Stat::make('Missed', number_format($s['missed_calls']))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->extraAttributes(['x-tooltip.raw' => 'Calls that were attempted but not answered — the line rang or connected but the recipient did not pick up.']),

            Stat::make('Did Not Process', number_format(max(0, $didNotProcess)))
                ->icon('heroicon-o-minus-circle')
                ->color('gray')
                ->description('Filtered before dialing')
                ->extraAttributes(['x-tooltip.raw' => 'Call records that were submitted to the system but never actually dialed — typically suppressed numbers, duplicates, or numbers excluded by campaign rules.']),

            Stat::make('Leads (Interested)', number_format($s['leads']))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->extraAttributes(['x-tooltip.raw' => 'Contacts who pressed 1 during the IVR prompt to indicate interest — the highest-intent response.']),

            Stat::make('More Info', number_format($s['more_info']))
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->extraAttributes(['x-tooltip.raw' => 'Contacts who pressed 2 during the IVR prompt — interested but wanting further details before committing.']),

            Stat::make('Unsubscribed', number_format($s['unsubscribed']))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->extraAttributes(['x-tooltip.raw' => 'Contacts who pressed the opt-out key during a call. They are automatically added to the Do Not Call list and will not receive further calls.']),

            Stat::make('Minutes Consumed', number_format($s['minutes_consumed']))
                ->icon('heroicon-o-clock')
                ->extraAttributes(['x-tooltip.raw' => 'Total billable minutes in the selected period — answered calls only, rounded up to the nearest minute with a minimum of 1 minute per answered call.']),
        ];
    }
}
