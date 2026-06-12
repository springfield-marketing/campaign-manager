<?php

use App\Modules\IVR\Models\CentralDatabaseExport;
use App\Modules\IVR\Models\IvrCampaign;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/ivr/database-export/{export}/download', function (CentralDatabaseExport $export) {
        abort_unless($export->status === CentralDatabaseExport::STATUS_COMPLETED, 404);
        abort_unless($export->storage_path && Storage::disk('local')->exists($export->storage_path), 404);

        return response()->download(
            Storage::disk('local')->path($export->storage_path),
            $export->file_name ?: 'central-database-export.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    })->name('ivr.database-export.download');

    Route::get('/ivr/campaigns/{campaign}/leads/export', function (IvrCampaign $campaign) {
        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Campaign', 'Call Time', 'Name', 'Phone', 'Email', 'Emirate', 'DTMF Outcome', 'Duration', 'Source']);

            $campaign->callRecords()
                ->with(['phoneNumber.client.primaryEmail', 'phoneNumber.firstSource'])
                ->whereIn('dtmf_outcome', ['interested', 'more_info'])
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
                            $client?->emirate,
                            $lead->dtmf_outcome,
                            gmdate('H:i:s', (int) $lead->total_duration_seconds),
                            $lead->phoneNumber?->firstSource?->source_name,
                        ]);
                    }
                });

            fclose($handle);
        }, "ivr-campaign-{$campaign->external_campaign_id}-leads.csv");
    })->name('ivr.campaign-leads.export');
});
