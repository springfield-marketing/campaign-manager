<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = WhatsAppCampaign::query()
            ->latest('started_at')
            ->paginate(20);

        $latestCampaign = WhatsAppCampaign::query()
            ->latest('started_at')
            ->first();

        $query = WhatsAppMessage::query()
            ->with(['campaign', 'phoneNumber.client'])
            ->latest('scheduled_at');

        if ($latestCampaign) {
            $query->where('whatsapp_campaign_id', $latestCampaign->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('phone')) {
            $query->whereHas('phoneNumber', fn ($q) => $q->where('normalized_phone', 'like', '%'.$request->string('phone').'%'));
        }

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->string('status'));
        }

        if ($request->filled('template')) {
            $query->where('template_name', $request->string('template'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date('date'));
        }

        return view('whatsapp::campaigns.index', [
            'campaigns' => $campaigns,
            'latestCampaign' => $latestCampaign,
            'messages' => $query->paginate(25, ['*'], 'messages'),
        ]);
    }

    public function show(WhatsAppCampaign $campaign): View
    {
        $row = $campaign->messages()
            ->selectRaw('count(*) as total_messages')
            ->selectRaw("sum(case when delivery_status = 'SENT'      then 1 else 0 end) as sent_count")
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when delivery_status = 'READ'      then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when delivery_status = 'REPLIED'   then 1 else 0 end) as replied_count")
            ->selectRaw("sum(case when delivery_status = 'FAILED'    then 1 else 0 end) as failed_count")
            ->first();

        $stats = [
            'total_messages'  => (int) ($row->total_messages ?? 0),
            'sent_count'      => (int) ($row->sent_count ?? 0),
            'delivered_count' => (int) ($row->delivered_count ?? 0),
            'read_count'      => (int) ($row->read_count ?? 0),
            'replied_count'   => (int) ($row->replied_count ?? 0),
            'failed_count'    => (int) ($row->failed_count ?? 0),
        ];

        $messages = $campaign->messages()
            ->with('phoneNumber.client')
            ->latest('scheduled_at')
            ->paginate(25);

        return view('whatsapp::campaigns.show', [
            'campaign' => $campaign,
            'stats' => $stats,
            'messages' => $messages,
        ]);
    }

    public function exportLeads(WhatsAppCampaign $campaign): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fileName = "whatsapp-campaign-{$campaign->name}-results.csv";

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Campaign',
                'Scheduled At',
                'Phone',
                'Name',
                'Template',
                'Status',
                'Clicked',
                'Quick Reply 1',
                'Quick Reply 2',
                'Quick Reply 3',
                'Failure Reason',
            ]);

            $campaign->messages()
                ->with('phoneNumber.client')
                ->latest('scheduled_at')
                ->chunk(500, function ($messages) use ($handle, $campaign): void {
                    foreach ($messages as $msg) {
                        fputcsv($handle, [
                            $campaign->name,
                            optional($msg->scheduled_at)->format('Y-m-d H:i:s'),
                            $msg->phoneNumber?->normalized_phone,
                            $msg->phoneNumber?->client?->full_name,
                            $msg->template_name,
                            $msg->delivery_status,
                            $msg->clicked ? 'Yes' : 'No',
                            $msg->quick_reply_1,
                            $msg->quick_reply_2,
                            $msg->quick_reply_3,
                            $msg->failure_reason,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
