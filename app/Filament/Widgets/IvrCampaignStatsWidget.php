<?php

namespace App\Filament\Widgets;

use App\Modules\IVR\Models\IvrCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        $totalCalls    = (int) $campaign->total_calls;
        $answeredCalls = (int) $campaign->answered_calls;
        $totalLeads    = (int) $campaign->leads_count + (int) $campaign->more_info_count;
        $answerRate    = $totalCalls > 0
            ? number_format(($answeredCalls / $totalCalls) * 100, 1).'% answer rate'
            : null;

        // FLOOR((s + 59) / 60) is ceiling-division for positive integers, works on
        // MySQL and SQLite without driver detection (replaces CEILING() vs cast trick).
        $timeConsumedMinutes = (int) $campaign->callRecords()
            ->selectRaw("coalesce(sum(case
                when lower(call_status) <> 'answered' then 0
                when total_duration_seconds <= 0 then 0
                when total_duration_seconds <= 60 then 1
                else floor((total_duration_seconds + 59) / 60)
            end), 0) as billable_minutes")
            ->value('billable_minutes');

        return [
            Stat::make('Total Calls', number_format($totalCalls))
                ->icon('heroicon-o-phone')
                ->extraAttributes(['x-tooltip.raw' => 'Total call attempts made for this campaign — includes answered, missed, and any calls that did not connect.']),

            Stat::make('Answered', number_format($answeredCalls))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->description($answerRate)
                ->extraAttributes(['x-tooltip.raw' => 'Calls where the recipient picked up. Answer rate is answered ÷ total calls.']),

            Stat::make('Missed', number_format((int) $campaign->unanswered_calls))
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->extraAttributes(['x-tooltip.raw' => 'Calls that were attempted but not answered — the line rang or connected but the recipient did not pick up.']),

            Stat::make('Leads', number_format($totalLeads))
                ->icon('heroicon-o-star')
                ->color('primary')
                ->description(number_format((int) $campaign->leads_count).' interested')
                ->extraAttributes(['x-tooltip.raw' => 'Combined warm leads — contacts who pressed 1 (Interested) or 2 (More Info) during the IVR prompt. The description shows the press-1-only count.']),

            Stat::make('More Info', number_format((int) $campaign->more_info_count))
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->extraAttributes(['x-tooltip.raw' => 'Contacts who pressed 2 during the IVR prompt — interested but wanting further details before committing.']),

            Stat::make('Unsubscribed', number_format((int) $campaign->unsubscribed_count))
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->extraAttributes(['x-tooltip.raw' => 'Contacts who pressed the opt-out key during this campaign. They are added to the Do Not Call list and will not be called again.']),

            Stat::make('Time Consumed', number_format($timeConsumedMinutes).' min')
                ->icon('heroicon-o-clock')
                ->extraAttributes(['x-tooltip.raw' => 'Billable minutes for this campaign — answered calls only, rounded up to the nearest minute with a minimum of 1 minute per answered call.']),
        ];
    }
}
