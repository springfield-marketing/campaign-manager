<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Models\IvrCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class IvrEfficiencyStatsWidget extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;
    protected static bool $isLazy = false;

    public int $year;
    public ?int $month = null;

    public function mount(int $year = 0, ?int $month = null): void
    {
        $this->year  = $year ?: now()->year;
        $this->month = $month;
    }

    #[On('ivr-filter-changed')]
    public function onFilterChanged(int $year, ?int $month): void
    {
        $this->year  = $year;
        $this->month = $month;
    }

    public function getHeading(): ?string
    {
        return 'Efficiency';
    }

    protected function getStats(): array
    {
        // Minutes per lead from pre-aggregated summaries
        $totals = DB::table('ivr_monthly_summaries')
            ->where('year', $this->year)
            ->when($this->month, fn ($q) => $q->where('month', $this->month))
            ->selectRaw('SUM(minutes_consumed) AS total_minutes, SUM(leads + more_info) AS total_leads')
            ->first();

        $totalMinutes = (int) ($totals->total_minutes ?? 0);
        $totalLeads   = (int) ($totals->total_leads   ?? 0);
        $minsPerLead  = $totalLeads > 0 ? round($totalMinutes / $totalLeads, 1) : null;

        // Best / worst campaign using pre-computed columns on ivr_campaigns
        $campaigns = IvrCampaign::query()
            ->whereNotNull('started_at')
            ->whereYear('started_at', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('started_at', $this->month))
            ->where('answered_calls', '>=', 20)
            ->get(['external_campaign_id', 'answered_calls', 'leads_count', 'more_info_count'])
            ->map(function (IvrCampaign $c): array {
                $leads = (int) $c->leads_count + (int) $c->more_info_count;
                $ans   = (int) $c->answered_calls;

                return [
                    'id'        => $c->external_campaign_id,
                    'answered'  => $ans,
                    'leads'     => $leads,
                    'lead_rate' => $ans > 0 ? round($leads / $ans * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('lead_rate')
            ->values();

        $best  = $campaigns->first();
        $worst = $campaigns->count() > 1 ? $campaigns->last() : null;

        $mplColor = match (true) {
            $minsPerLead === null        => 'gray',
            $minsPerLead <= 30           => 'success',
            $minsPerLead <= 60           => 'warning',
            default                      => 'danger',
        };

        $stats = [
            Stat::make('Minutes per Lead', $minsPerLead !== null ? number_format($minsPerLead, 1) . ' min' : '—')
                ->icon('heroicon-o-clock')
                ->color($mplColor)
                ->description('Minutes consumed per lead generated')
                ->extraAttributes(['x-tooltip.raw' => 'Total minutes consumed divided by total leads (press 1 + press 2). Lower is better — a rising number means it takes more minutes to produce each lead.']),
        ];

        if ($best !== null) {
            $stats[] = Stat::make('Best Campaign', $best['id'])
                ->icon('heroicon-o-trophy')
                ->color('success')
                ->description($best['lead_rate'] . '% lead rate · ' . number_format($best['answered']) . ' answered')
                ->extraAttributes(['x-tooltip.raw' => 'Campaign with the highest lead conversion rate in this period (min. 20 answered calls). Lead rate = total leads ÷ answered calls.']);
        }

        if ($worst !== null) {
            $stats[] = Stat::make('Worst Campaign', $worst['id'])
                ->icon('heroicon-o-arrow-trending-down')
                ->color('danger')
                ->description($worst['lead_rate'] . '% lead rate · ' . number_format($worst['answered']) . ' answered')
                ->extraAttributes(['x-tooltip.raw' => 'Campaign with the lowest lead conversion rate in this period (min. 20 answered calls). Compare against the best campaign to identify what is working differently.']);
        }

        return $stats;
    }
}
