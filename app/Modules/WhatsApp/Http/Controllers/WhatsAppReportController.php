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

        $campaignBreakdown = WhatsAppCampaign::query()
            ->whereHas('messages', function ($q) use ($year, $month): void {
                $q->whereYear('scheduled_at', $year)
                    ->when($month, fn ($q2) => $q2->whereMonth('scheduled_at', $month));
            })
            ->withCount([
                'messages',
                'messages as delivered_count' => fn ($q) => $q->where('delivery_status', 'DELIVERED'),
                'messages as read_count' => fn ($q) => $q->where('delivery_status', 'READ'),
                'messages as failed_count' => fn ($q) => $q->where('delivery_status', 'FAILED'),
                'messages as clicked_count' => fn ($q) => $q->where('clicked', true),
            ])
            ->latest('started_at')
            ->paginate(20, ['*'], 'campaign_page')
            ->withQueryString();

        return view('whatsapp::reports.index', [
            'year' => $year,
            'month' => $month,
            'summary' => $summary,
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
                'total_messages' => (int) $row->total_messages,
                'delivered'      => (int) $row->delivered_count,
                'read'           => (int) $row->read_count,
                'failed'         => (int) $row->failed_count,
                'clicked'        => (int) $row->clicked_count,
            ];
        }

        $aggregate = WhatsAppMessage::query()
            ->whereYear('scheduled_at', $year)
            ->when($month, fn ($q) => $q->whereMonth('scheduled_at', $month))
            ->selectRaw('count(*) as total_messages')
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when delivery_status = 'READ' then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when delivery_status = 'FAILED' then 1 else 0 end) as failed_count")
            ->selectRaw('sum(case when clicked = true then 1 else 0 end) as clicked_count')
            ->first();

        return [
            'total_messages' => (int) ($aggregate->total_messages ?? 0),
            'delivered'      => (int) ($aggregate->delivered_count ?? 0),
            'read'           => (int) ($aggregate->read_count ?? 0),
            'failed'         => (int) ($aggregate->failed_count ?? 0),
            'clicked'        => (int) ($aggregate->clicked_count ?? 0),
        ];
    }
}
