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
                ->icon('heroicon-o-calendar')
                ->extraAttributes(['x-tooltip.raw' => 'The contracted number of minutes for the selected month. Calls within this limit are billed at the standard rate.']),

            Stat::make('Remaining', number_format($budget['minutes_remaining']) . ' min')
                ->icon('heroicon-o-clock')
                ->color($overQuota ? 'danger' : 'success')
                ->description($overQuota
                    ? 'Exceeded by ' . number_format($exceeded) . ' min — over-quota rate applies'
                    : null)
                ->extraAttributes(['x-tooltip.raw' => 'Minutes left in the monthly quota. If this is negative, the quota has been exceeded and the higher over-quota rate is being charged for the excess.']),

            Stat::make('Budget / Day', number_format($budget['minutes_per_day']) . ' min/day')
                ->icon('heroicon-o-chart-bar')
                ->color($overQuota ? 'danger' : null)
                ->description($budget['remaining_working_days'] . ' working days left')
                ->extraAttributes(['x-tooltip.raw' => 'Remaining quota minutes divided by remaining working days (Sunday–Thursday) — the daily target to use up the quota evenly without going over.']),

            Stat::make('Projected (month end)', number_format($budget['projected_minutes']) . ' min')
                ->icon('heroicon-o-arrow-trending-up')
                ->color($budget['projected_over_quota'] ? 'danger' : 'success')
                ->description($budget['projected_over_quota']
                    ? 'On pace to exceed quota by ' . number_format($budget['projected_overage']) . ' min'
                    : 'On pace to stay within quota')
                ->extraAttributes(['x-tooltip.raw' => 'Current usage extrapolated over the whole month\'s working days. If this exceeds the quota, the run-rate is too high and a budget alert is raised.']),
        ];
    }
}
