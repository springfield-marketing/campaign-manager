<?php

namespace App\Modules\IVR\Support;

use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IvrReportData
{
    /**
     * @return array{
     *     year: int,
     *     month: int|null,
     *     summary: array<string, float|int>,
     *     campaignBreakdown: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *     monthlyBudget: array<string, mixed>|null,
     *     blendedRate: float
     * }
     */
    public function forPeriod(int $year, ?int $month = null): array
    {
        $driver = DB::connection()->getDriverName();
        $billableMinutesExpression = $driver === 'sqlite'
            ? "coalesce(sum(case
                when call_status <> 'Answered' then 0
                when total_duration_seconds <= 0 then 0
                when total_duration_seconds <= 60 then 1
                else cast((total_duration_seconds + 59) / 60 as integer)
            end), 0)"
            : "coalesce(sum(case
                when call_status <> 'Answered' then 0
                when total_duration_seconds <= 0 then 0
                when total_duration_seconds <= 60 then 1
                else ceiling(total_duration_seconds / 60.0)
            end), 0)";

        $summaryCacheKey = "ivr:reports:v3:summary:{$year}:".($month ?? 'all');

        $summary = Cache::remember($summaryCacheKey, now()->addMinutes(2), function () use ($year, $month, $billableMinutesExpression): array {
            $aggregate = IvrCallRecord::query()
                ->when($year, fn ($query) => $query->whereYear('call_time', $year))
                ->when($month, fn ($query) => $query->whereMonth('call_time', $month))
                ->selectRaw('count(*) as total_calls')
                ->selectRaw("sum(case when call_status = 'Answered' then 1 else 0 end) as answered_calls")
                ->selectRaw("sum(case when call_status = 'Missed' then 1 else 0 end) as missed_calls")
                ->selectRaw("sum(case when dtmf_outcome = 'interested' then 1 else 0 end) as leads")
                ->selectRaw("sum(case when dtmf_outcome = 'more_info' then 1 else 0 end) as more_info")
                ->selectRaw("sum(case when dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed")
                ->selectRaw($billableMinutesExpression.' as minutes_consumed')
                ->first();

            return [
                'total_calls' => (int) ($aggregate->total_calls ?? 0),
                'answered_calls' => (int) ($aggregate->answered_calls ?? 0),
                'missed_calls' => (int) ($aggregate->missed_calls ?? 0),
                'leads' => (int) ($aggregate->leads ?? 0),
                'more_info' => (int) ($aggregate->more_info ?? 0),
                'unsubscribed' => (int) ($aggregate->unsubscribed ?? 0),
                'minutes_consumed' => (int) ($aggregate->minutes_consumed ?? 0),
            ];
        });

        $settings = IvrSettings::current();
        $monthlyBudget = null;
        $blendedRate = (float) $settings->price_per_minute_under;

        $isCurrentMonth = $month !== null
            && $year === now()->year
            && $month === now()->month;

        if ($isCurrentMonth) {
            $monthlyBudget = $this->calculateMonthlyBudget($summary['minutes_consumed'], $settings);
        }

        if ($summary['minutes_consumed'] > 0) {
            $quota = $settings->monthly_minutes_quota;
            $underRate = (float) $settings->price_per_minute_under;
            $overRate = (float) $settings->price_per_minute_over;
            $used = $summary['minutes_consumed'];

            $totalCost = min($used, $quota) * $underRate + max(0, $used - $quota) * $overRate;
            $blendedRate = $totalCost / $used;
        }

        return [
            'year' => $year,
            'month' => $month,
            'summary' => $summary,
            'campaignBreakdown' => $this->campaignBreakdown($year, $month, $billableMinutesExpression, $blendedRate),
            'monthlyBudget' => $monthlyBudget,
            'blendedRate' => $blendedRate,
        ];
    }

    /**
     * @return array{
     *     minutes_quota: int,
     *     minutes_used: int,
     *     minutes_remaining: int,
     *     remaining_working_days: int,
     *     minutes_per_day: float
     * }
     */
    private function calculateMonthlyBudget(int $minutesUsed, IvrSettings $settings): array
    {
        $quota = $settings->monthly_minutes_quota;
        $remaining = max(0, $quota - $minutesUsed);
        $workingDays = $this->remainingWorkingDays();

        return [
            'minutes_quota' => $quota,
            'minutes_used' => $minutesUsed,
            'minutes_remaining' => $remaining,
            'remaining_working_days' => $workingDays,
            'minutes_per_day' => $workingDays > 0 ? round($remaining / $workingDays) : 0,
        ];
    }

    private function remainingWorkingDays(): int
    {
        $today = now()->startOfDay();
        $endOfMonth = now()->endOfMonth()->startOfDay();
        $count = 0;

        $current = $today->copy();
        while ($current->lte($endOfMonth)) {
            // 0 = Sunday, exclude it
            if ($current->dayOfWeek !== Carbon::SUNDAY) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    private function campaignBreakdown(int $year, ?int $month, string $billableMinutesExpression, float $blendedRate)
    {
        return IvrCallRecord::query()
            ->join('ivr_campaigns', 'ivr_campaigns.id', '=', 'ivr_call_records.ivr_campaign_id')
            ->when($year, fn ($query) => $query->whereYear('ivr_call_records.call_time', $year))
            ->when($month, fn ($query) => $query->whereMonth('ivr_call_records.call_time', $month))
            ->groupBy('ivr_campaigns.id', 'ivr_campaigns.external_campaign_id')
            ->selectRaw('ivr_campaigns.id as campaign_id')
            ->selectRaw('ivr_campaigns.external_campaign_id')
            ->selectRaw('min(ivr_call_records.call_time) as campaign_started_at')
            ->selectRaw('max(ivr_call_records.call_time) as campaign_completed_at')
            ->selectRaw('count(*) as calls_count')
            ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'more_info' then 1 else 0 end) as more_info_count_filtered")
            ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'interested' then 1 else 0 end) as leads_count_filtered")
            ->selectRaw("sum(case when ivr_call_records.call_status = 'Answered' then 1 else 0 end) as answered_calls")
            ->selectRaw("sum(case when ivr_call_records.dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed_calls")
            ->selectRaw("{$billableMinutesExpression} as minutes_used")
            ->selectRaw("({$billableMinutesExpression}) * ? as campaign_cost", [$blendedRate])
            ->orderByDesc('campaign_completed_at')
            ->orderByDesc('campaign_started_at')
            ->paginate(20, ['*'], 'campaign_page')
            ->withQueryString();
    }
}
