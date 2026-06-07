<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Models\IvrCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class IvrCampaignStatsWidget extends StatsOverviewWidget
{
    public int|string $campaignId;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $campaign = IvrCampaign::query()->find($this->campaignId);

        if (! $campaign) {
            return [];
        }

        $totalCalls = (int) $campaign->total_calls;
        $answeredCalls = (int) $campaign->answered_calls;
        $totalLeads = (int) $campaign->leads_count + (int) $campaign->more_info_count;
        $answerRate = $totalCalls > 0
            ? number_format(($answeredCalls / $totalCalls) * 100, 1).'% answer rate'
            : null;

        $driver = DB::connection()->getDriverName();
        $billableExpr = $driver === 'sqlite'
            ? "coalesce(sum(case when lower(call_status) <> 'answered' then 0 when total_duration_seconds <= 0 then 0 when total_duration_seconds <= 60 then 1 else cast((total_duration_seconds + 59) / 60 as integer) end), 0)"
            : "coalesce(sum(case when lower(call_status) <> 'answered' then 0 when total_duration_seconds <= 0 then 0 when total_duration_seconds <= 60 then 1 else ceiling(total_duration_seconds / 60.0) end), 0)";

        $timeConsumedMinutes = (int) $campaign->callRecords()
            ->selectRaw($billableExpr.' as billable_minutes')
            ->value('billable_minutes');

        return [
            Stat::make('Total Calls', number_format($totalCalls))
                ->icon('heroicon-o-phone'),

            Stat::make('Answered', number_format($answeredCalls))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($answerRate),

            Stat::make('Missed', number_format((int) $campaign->unanswered_calls))
                ->icon('heroicon-o-x-circle')
                ->color('warning'),

            Stat::make('Leads', number_format($totalLeads))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->description(number_format((int) $campaign->leads_count).' interested'),

            Stat::make('More Info', number_format((int) $campaign->more_info_count))
                ->icon('heroicon-o-information-circle')
                ->color('info'),

            Stat::make('Unsubscribed', number_format((int) $campaign->unsubscribed_count))
                ->icon('heroicon-o-no-symbol')
                ->color('danger'),

            Stat::make('Time Consumed', number_format($timeConsumedMinutes).' min')
                ->icon('heroicon-o-clock'),
        ];
    }
}
