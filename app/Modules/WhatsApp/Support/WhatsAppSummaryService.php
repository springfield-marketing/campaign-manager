<?php

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppSummaryService
{
    public function recompute(int $year, ?int $month): void
    {
        $range = $month
            ? [Carbon::create($year, $month, 1)->startOfDay(), Carbon::create($year, $month, 1)->endOfMonth()->endOfDay()]
            : [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];

        $aggregate = WhatsAppMessage::query()
            ->whereBetween('scheduled_at', $range)
            ->selectRaw('count(*) as total_messages')
            ->selectRaw("sum(case when delivery_status = 'SENT'      then 1 else 0 end) as sent_count")
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when delivery_status = 'READ'      then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when delivery_status = 'REPLIED'   then 1 else 0 end) as replied_count")
            ->selectRaw("sum(case when delivery_status = 'FAILED'    then 1 else 0 end) as failed_count")
            ->selectRaw("sum(case when delivery_status = 'STOPPED'   then 1 else 0 end) as unsubscribed_count")
            ->first();

        DB::table('whatsapp_monthly_summaries')->upsert(
            [
                'year'               => $year,
                'month'              => $month,
                'total_messages'     => (int) ($aggregate->total_messages ?? 0),
                'sent_count'         => (int) ($aggregate->sent_count ?? 0),
                'delivered_count'    => (int) ($aggregate->delivered_count ?? 0),
                'read_count'         => (int) ($aggregate->read_count ?? 0),
                'replied_count'      => (int) ($aggregate->replied_count ?? 0),
                'failed_count'       => (int) ($aggregate->failed_count ?? 0),
                'unsubscribed_count' => (int) ($aggregate->unsubscribed_count ?? 0),
                'computed_at'        => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            ['year', 'month'],
            ['total_messages', 'sent_count', 'delivered_count', 'read_count', 'replied_count', 'failed_count', 'unsubscribed_count', 'computed_at', 'updated_at'],
        );
    }

    public function recomputeAllMonths(): void
    {
        // Use EXTRACT only here — this runs as a one-time admin command, not on page load.
        $months = WhatsAppMessage::query()
            ->selectRaw('EXTRACT(YEAR FROM scheduled_at)::int as year, EXTRACT(MONTH FROM scheduled_at)::int as month')
            ->whereNotNull('scheduled_at')
            ->groupByRaw('EXTRACT(YEAR FROM scheduled_at), EXTRACT(MONTH FROM scheduled_at)')
            ->orderByRaw('year, month')
            ->get();

        foreach ($months as $row) {
            $this->recompute((int) $row->year, (int) $row->month);
        }
    }
}
