<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Support\IvrReportData;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class IvrBudgetWidget extends StatsOverviewWidget
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

    public function getHeading(): ?string
    {
        return 'Monthly Budget';
    }

    protected function getStats(): array
    {
        $data   = app(IvrReportData::class)->forPeriod($this->year, $this->month);
        $budget = $data['monthlyBudget'];

        if ($budget === null) {
            return [];
        }

        $overQuota = $budget['minutes_used'] > $budget['minutes_quota'];
        $exceeded  = $overQuota ? $budget['minutes_used'] - $budget['minutes_quota'] : 0;

        return [
            Stat::make('Monthly Quota', number_format($budget['minutes_quota']) . ' min')
                ->icon('heroicon-o-calendar'),

            Stat::make('Remaining', number_format($budget['minutes_remaining']) . ' min')
                ->icon('heroicon-o-clock')
                ->color($overQuota ? 'danger' : 'success')
                ->description($overQuota
                    ? 'Exceeded by ' . number_format($exceeded) . ' min — over-quota rate applies'
                    : null),

            Stat::make('Budget / Day', number_format($budget['minutes_per_day']) . ' min/day')
                ->icon('heroicon-o-chart-bar')
                ->color($overQuota ? 'danger' : null)
                ->description($budget['remaining_working_days'] . ' working days left'),
        ];
    }
}
