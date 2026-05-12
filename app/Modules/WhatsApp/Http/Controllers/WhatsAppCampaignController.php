<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Enums\WhatsAppImportType;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use App\Modules\WhatsApp\Models\WhatsAppImport;
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

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->string('status'));
        }

        if ($request->filled('template')) {
            $query->where('template_name', $request->string('template'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date('date'));
        }

        $imports = WhatsAppImport::query()
            ->where('type', WhatsAppImportType::CampaignResults->value)
            ->latest()
            ->paginate(15, ['*'], 'imports');

        return view('whatsapp::campaigns.index', [
            'imports' => $imports,
            'campaigns' => $campaigns,
            'latestCampaign' => $latestCampaign,
            'messages' => $query->paginate(25, ['*'], 'messages'),
        ]);
    }

    public function show(WhatsAppCampaign $campaign): View
    {
        $stats = [
            'total_messages' => $campaign->messages()->count(),
            'delivered_count' => $campaign->messages()->where('delivery_status', 'DELIVERED')->count(),
            'read_count' => $campaign->messages()->where('delivery_status', 'READ')->count(),
            'failed_count' => $campaign->messages()->where('delivery_status', 'FAILED')->count(),
            'clicked_count' => $campaign->messages()->where('clicked', true)->count(),
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
