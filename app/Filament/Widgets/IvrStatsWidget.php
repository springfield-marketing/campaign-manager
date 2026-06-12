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
                ->icon('heroicon-o-phone'),

            Stat::make('Answered', number_format($s['answered_calls']))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($s['total_calls'] > 0
                    ? number_format($s['answered_calls'] / $s['total_calls'] * 100, 1) . '% answer rate'
                    : null
                ),

            Stat::make('Missed', number_format($s['missed_calls']))
                ->icon('heroicon-o-x-circle')
                ->color('warning'),

            Stat::make('Did Not Process', number_format(max(0, $didNotProcess)))
                ->icon('heroicon-o-minus-circle')
                ->color('gray')
                ->description('Filtered before dialing'),

            Stat::make('Leads (Interested)', number_format($s['leads']))
                ->icon('heroicon-o-star')
                ->color('primary'),

            Stat::make('More Info', number_format($s['more_info']))
                ->icon('heroicon-o-information-circle')
                ->color('info'),

            Stat::make('Unsubscribed', number_format($s['unsubscribed']))
                ->icon('heroicon-o-no-symbol')
                ->color('danger'),

            Stat::make('Minutes Consumed', number_format($s['minutes_consumed']))
                ->icon('heroicon-o-clock'),
        ];
    }
}
