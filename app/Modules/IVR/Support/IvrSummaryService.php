<?php

namespace App\Modules\IVR\Support;

use App\Modules\IVR\Models\IvrCallRecord;
use Illuminate\Support\Facades\DB;

class IvrSummaryService
{
    public function recompute(int $year, ?int $month): void
    {
        $driver = DB::connection()->getDriverName();
        $billableMinutes = $driver === 'sqlite'
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

        $aggregate = IvrCallRecord::query()
            ->whereYear('call_time', $year)
            ->when($month, fn ($q) => $q->whereMonth('call_time', $month))
            ->selectRaw('count(*) as total_calls')
            ->selectRaw("sum(case when call_status = 'Answered' then 1 else 0 end) as answered_calls")
            ->selectRaw("sum(case when call_status = 'Missed' then 1 else 0 end) as missed_calls")
            ->selectRaw("sum(case when dtmf_outcome = 'interested' then 1 else 0 end) as leads")
            ->selectRaw("sum(case when dtmf_outcome = 'more_info' then 1 else 0 end) as more_info")
            ->selectRaw("sum(case when dtmf_outcome = 'unsubscribe' then 1 else 0 end) as unsubscribed")
            ->selectRaw("{$billableMinutes} as minutes_consumed")
            ->first();

        DB::table('ivr_monthly_summaries')->upsert(
            [
                'year' => $year,
                'month' => $month,
                'total_calls' => (int) ($aggregate->total_calls ?? 0),
                'answered_calls' => (int) ($aggregate->answered_calls ?? 0),
                'missed_calls' => (int) ($aggregate->missed_calls ?? 0),
                'leads' => (int) ($aggregate->leads ?? 0),
                'more_info' => (int) ($aggregate->more_info ?? 0),
                'unsubscribed' => (int) ($aggregate->unsubscribed ?? 0),
                'minutes_consumed' => (int) ($aggregate->minutes_consumed ?? 0),
                'computed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['year', 'month'],
            ['total_calls', 'answered_calls', 'missed_calls', 'leads', 'more_info', 'unsubscribed', 'minutes_consumed', 'computed_at', 'updated_at'],
        );
    }

    public function recomputeAllMonths(): void
    {
        $months = IvrCallRecord::query()
            ->selectRaw('EXTRACT(YEAR FROM call_time)::int as year, EXTRACT(MONTH FROM call_time)::int as month')
            ->whereNotNull('call_time')
            ->groupByRaw('EXTRACT(YEAR FROM call_time), EXTRACT(MONTH FROM call_time)')
            ->orderByRaw('year, month')
            ->get();

        foreach ($months as $row) {
            $this->recompute((int) $row->year, (int) $row->month);
        }
    }
}
