<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IvrCampaignResultController extends Controller
{
    private const LEAD_OUTCOMES = ['interested', 'more_info'];

    public function audio(IvrCampaign $campaign): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $campaign->loadMissing('script');

        $path = $campaign->script?->audio_file_path
            ? storage_path('app/private/'.$campaign->script->audio_file_path)
            : ($campaign->audio_file_path ? storage_path('app/private/'.$campaign->audio_file_path) : null);

        abort_unless($path && file_exists($path), 404);

        return response()->file($path);
    }

    public function assignScript(Request $request, IvrCampaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'ivr_script_id' => ['nullable', 'integer', 'exists:ivr_scripts,id'],
        ]);

        $campaign->update(['ivr_script_id' => $validated['ivr_script_id'] ?? null]);

        return back()->with('status', 'Script assigned.');
    }

    public function exportLeads(IvrCampaign $campaign): StreamedResponse
    {
        $fileName = "ivr-campaign-{$campaign->external_campaign_id}-leads.csv";

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Campaign',
                'Call Time',
                'Name',
                'Phone',
                'Email',
                'Detected Country',
                'Nationality',
                'Emirate',
                'Gender',
                'Interest',
                'Call Status',
                'DTMF Outcome',
                'Duration',
                'Duration Seconds',
                'Source File',
            ]);

            $campaign->callRecords()
                ->with(['import', 'phoneNumber.client.primaryEmail'])
                ->whereIn('dtmf_outcome', self::LEAD_OUTCOMES)
                ->latest('call_time')
                ->chunk(500, function ($leads) use ($handle, $campaign): void {
                    foreach ($leads as $lead) {
                        $client = $lead->phoneNumber?->client;

                        fputcsv($handle, [
                            $campaign->external_campaign_id,
                            optional($lead->call_time)->format('Y-m-d H:i:s'),
                            $client?->full_name,
                            $lead->phoneNumber?->normalized_phone,
                            $client?->primaryEmail?->email,
                            $lead->phoneNumber?->detected_country,
                            $client?->nationality,
                            $client?->emirate,
                            $client?->gender,
                            $client?->interest,
                            $lead->call_status,
                            $lead->dtmf_outcome,
                            gmdate('H:i:s', (int) $lead->total_duration_seconds),
                            $lead->total_duration_seconds,
                            $lead->import?->original_file_name,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->date('from')->startOfDay();
        $to = $request->date('to')->endOfDay();
        $fileName = sprintf('ivr-campaign-results-%s-to-%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        return response()->streamDownload(function () use ($from, $to): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Campaign',
                'Call UUID',
                'Call Time',
                'Name',
                'Phone',
                'Email',
                'Country',
                'Nationality',
                'Community',
                'Resident',
                'Emirate',
                'Gender',
                'Interest',
                'Call Direction',
                'Call Status',
                'Customer Status',
                'Agent Status',
                'DTMF Outcome',
                'DTMF Extensions',
                'Duration',
                'Duration Seconds',
                'Talk Time',
                'Talk Time Seconds',
                'Disposition',
                'Sub Disposition',
                'Hangup By',
                'Credits Deducted',
                'Source File',
            ]);

            IvrCallRecord::query()
                ->with(['campaign', 'import', 'phoneNumber.client.primaryEmail', 'phoneNumber.client.country', 'phoneNumber.client.region', 'phoneNumber.client.community'])
                ->whereBetween('call_time', [$from, $to])
                ->orderBy('call_time')
                ->orderBy('id')
                ->chunk(1000, function ($records) use ($handle): void {
                    foreach ($records as $record) {
                        $client = $record->phoneNumber?->client;
                        $dtmfExtensions = is_array($record->dtmf_extensions)
                            ? implode('|', $record->dtmf_extensions)
                            : null;

                        fputcsv($handle, [
                            $record->campaign?->external_campaign_id,
                            $record->external_call_uuid,
                            optional($record->call_time)->format('Y-m-d H:i:s'),
                            $client?->full_name,
                            $record->phoneNumber?->normalized_phone,
                            $client?->primary_email_address,
                            $client?->country?->name,
                            $client?->nationality,
                            $client?->community?->name,
                            $client?->resident,
                            $client?->region?->name,
                            $client?->gender,
                            $client?->interest,
                            $record->call_direction,
                            $record->call_status,
                            $record->customer_status,
                            $record->agent_status,
                            $record->dtmf_outcome,
                            $dtmfExtensions,
                            gmdate('H:i:s', (int) $record->total_duration_seconds),
                            $record->total_duration_seconds,
                            gmdate('H:i:s', (int) $record->talk_time_seconds),
                            $record->talk_time_seconds,
                            $record->disposition,
                            $record->sub_disposition,
                            $record->hangup_by,
                            $record->credits_deducted,
                            $record->import?->original_file_name,
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
