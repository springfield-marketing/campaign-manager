<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WhatsAppReportController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $month = $request->filled('month') ? (int) $request->input('month') : now()->month;

        $summary = $this->summaryForPeriod($year, $month);

        $total     = $summary['total'];
        $reached   = $summary['delivered'] + $summary['read'] + $summary['replied'];
        $rates = [
            'delivery_rate' => $total > 0 ? round($reached / $total * 100, 1) : 0,
            'read_rate'     => $reached > 0 ? round(($summary['read'] + $summary['replied']) / $reached * 100, 1) : 0,
            'reply_rate'    => $total > 0 ? round($summary['replied'] / $total * 100, 1) : 0,
        ];

        $campaignBreakdown = WhatsAppCampaign::query()
            ->whereHas('messages', function ($q) use ($year, $month): void {
                $q->whereYear('scheduled_at', $year)
                    ->when($month, fn ($q2) => $q2->whereMonth('scheduled_at', $month));
            })
            ->withCount([
                'messages',
                'messages as sent_count'      => fn ($q) => $q->where('delivery_status', 'SENT'),
                'messages as delivered_count' => fn ($q) => $q->where('delivery_status', 'DELIVERED'),
                'messages as read_count'      => fn ($q) => $q->where('delivery_status', 'READ'),
                'messages as replied_count'   => fn ($q) => $q->where('delivery_status', 'REPLIED'),
                'messages as failed_count'    => fn ($q) => $q->where('delivery_status', 'FAILED'),
            ])
            ->latest('started_at')
            ->paginate(20, ['*'], 'campaign_page')
            ->withQueryString();

        return view('whatsapp::reports.index', [
            'year'              => $year,
            'month'             => $month,
            'summary'           => $summary,
            'rates'             => $rates,
            'campaignBreakdown' => $campaignBreakdown,
        ]);
    }

    /** @return array<string, int> */
    private function summaryForPeriod(int $year, ?int $month): array
    {
        $row = DB::table('whatsapp_monthly_summaries')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($row !== null) {
            return [
                'total'     => (int) $row->total_messages,
                'sent'      => (int) $row->sent_count,
                'delivered' => (int) $row->delivered_count,
                'read'      => (int) $row->read_count,
                'replied'   => (int) $row->replied_count,
                'failed'    => (int) $row->failed_count,
            ];
        }

        $aggregate = WhatsAppMessage::query()
            ->whereYear('scheduled_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('scheduled_at', $month))
            ->selectRaw('count(*) as total_messages')
            ->selectRaw("sum(case when delivery_status = 'SENT'      then 1 else 0 end) as sent_count")
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when delivery_status = 'READ'      then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when delivery_status = 'REPLIED'   then 1 else 0 end) as replied_count")
            ->selectRaw("sum(case when delivery_status = 'FAILED'    then 1 else 0 end) as failed_count")
            ->first();

        return [
            'total'     => (int) ($aggregate->total_messages ?? 0),
            'sent'      => (int) ($aggregate->sent_count ?? 0),
            'delivered' => (int) ($aggregate->delivered_count ?? 0),
            'read'      => (int) ($aggregate->read_count ?? 0),
            'replied'   => (int) ($aggregate->replied_count ?? 0),
            'failed'    => (int) ($aggregate->failed_count ?? 0),
        ];
    }
}
