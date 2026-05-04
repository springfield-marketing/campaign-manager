<?php

namespace App\Modules\IVR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\ContactSuppression;
use App\Modules\IVR\Jobs\ProcessIvrCampaignResultsImport;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\BillableDuration;
use App\Modules\IVR\Support\IvrImportStatusPayload;
use App\Modules\IVR\Support\NumberEligibilityService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class IvrCampaignResultController extends Controller
{
    private const LEAD_OUTCOMES = ['interested', 'more_info'];

    public function index(Request $request): View
    {
        $resultImports = IvrImport::query()
            ->where('type', 'campaign_results')
            ->latest()
            ->paginate(10, ['*'], 'imports');

        $importCampaignReferences = $resultImports->getCollection()
            ->map(fn (IvrImport $import): string => (string) (data_get($import->summary, 'order_number') ?: pathinfo($import->original_file_name, PATHINFO_FILENAME)))
            ->filter()
            ->unique()
            ->values();

        $importCampaigns = IvrCampaign::query()
            ->whereIn('external_campaign_id', $importCampaignReferences)
            ->get()
            ->keyBy('external_campaign_id');

        $latestImport = IvrImport::query()
            ->where('type', 'campaign_results')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->whereNull('reverted_at')
            ->latest()
            ->get()
            ->first(fn (IvrImport $import): bool => (bool) (data_get($import->summary, 'order_number') ?: pathinfo($import->original_file_name, PATHINFO_FILENAME)));

        $latestCampaignReference = $latestImport
            ? (string) (data_get($latestImport->summary, 'order_number') ?: pathinfo($latestImport->original_file_name, PATHINFO_FILENAME))
            : null;

        $latestCampaign = $latestCampaignReference
            ? IvrCampaign::query()->where('external_campaign_id', $latestCampaignReference)->first()
            : null;

        $query = IvrCallRecord::query()
            ->with(['campaign', 'phoneNumber.client'])
            ->latest('call_time');

        if ($latestCampaign) {
            $query->where('ivr_campaign_id', $latestCampaign->id);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('outcome')) {
            $query->where('dtmf_outcome', $request->string('outcome'));
        }

        if ($request->filled('call_status')) {
            $query->where('call_status', $request->string('call_status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('call_time', $request->date('date'));
        }

        return view('ivr::results.index', [
            'imports' => $resultImports,
            'importCampaigns' => $importCampaigns,
            'latestCampaign' => $latestCampaign,
            'results' => $query->paginate(25, ['*'], 'results'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            ],
            [
                'file.uploaded' => 'The file could not be uploaded because it is larger than the current PHP upload limit. Increase upload_max_filesize and post_max_size, then try again.',
                'file.max' => 'The file must be 50 MB or smaller.',
            ],
        );

        $originalFileName = $validated['file']->getClientOriginalName();

        $existingImport = IvrImport::query()
            ->where('type', 'campaign_results')
            ->where('original_file_name', $originalFileName)
            ->whereNull('reverted_at')
            ->exists();

        if ($existingImport) {
            return back()
                ->withErrors(['file' => "A campaign results import named {$originalFileName} already exists. Rename the file if this is intentionally a new upload."])
                ->withInput();
        }

        $storedPath = $validated['file']->store('ivr/imports/results', 'local');

        $import = IvrImport::create([
            'type' => 'campaign_results',
            'status' => 'pending',
            'original_file_name' => $originalFileName,
            'stored_file_name' => basename($storedPath),
            'storage_path' => $storedPath,
            'uploaded_by' => $request->user()?->id,
        ]);

        $import->broadcastProgress();

        ProcessIvrCampaignResultsImport::dispatch($import->id);

        return redirect()
            ->route('modules.ivr.results.index')
            ->with('status', 'Campaign results import queued successfully.');
    }

    public function status(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->take(50)
            ->values();

        $imports = IvrImport::query()
            ->where('type', 'campaign_results')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (IvrImport $import): array => IvrImportStatusPayload::make($import))
            ->values();

        return response()->json(['imports' => $imports]);
    }

    public function show(IvrCampaign $campaign): View
    {
        $stats = $this->campaignStats($campaign);

        $leads = $campaign->callRecords()
            ->with('phoneNumber.client')
            ->whereIn('dtmf_outcome', self::LEAD_OUTCOMES)
            ->latest('call_time')
            ->paginate(25);

        return view('ivr::results.show', [
            'campaign' => $campaign,
            'stats' => $stats,
            'leads' => $leads,
        ]);
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
                'Country',
                'Nationality',
                'Community',
                'Resident',
                'City',
                'Gender',
                'Interest',
                'Call Status',
                'DTMF Outcome',
                'Duration',
                'Duration Seconds',
                'Source File',
            ]);

            $campaign->callRecords()
                ->with(['import', 'phoneNumber.client'])
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
                            $client?->email,
                            $client?->country,
                            $client?->nationality,
                            $client?->community,
                            $client?->resident,
                            $client?->city,
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

    public function destroy(Request $request, IvrImport $import, NumberEligibilityService $eligibilityService): RedirectResponse
    {
        if ($import->type !== 'campaign_results') {
            abort(404);
        }

        if (in_array($import->status, ['pending', 'processing'], true)) {
            return back()->with('status', 'This campaign import is still running and cannot be reverted yet.');
        }

        if ($import->reverted_at !== null) {
            return back()->with('status', 'This campaign import has already been reverted.');
        }

        $validated = $request->validate([
            'revert_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $affectedPhoneIds = collect();

        DB::transaction(function () use ($import, $request, $validated, &$affectedPhoneIds): void {
            /** @var EloquentCollection<int, IvrCallRecord> $callRecords */
            $callRecords = $import->callRecords()->with('campaign')->get();

            $affectedPhoneIds = $callRecords
                ->pluck('client_phone_number_id')
                ->filter()
                ->unique()
                ->values();

            $campaignIds = $callRecords
                ->pluck('ivr_campaign_id')
                ->filter()
                ->unique()
                ->values();

            $campaignReferences = $callRecords
                ->pluck('campaign.external_campaign_id')
                ->filter()
                ->unique()
                ->values();

            $summaryCampaignReference = data_get($import->summary, 'order_number');

            if ($summaryCampaignReference) {
                $campaignReferences->push((string) $summaryCampaignReference);
            }

            $campaignReferences = $campaignReferences->unique()->values();

            ClientSource::query()
                ->where('channel', 'ivr')
                ->where('source_type', 'campaign_result')
                ->where('source_file_name', $import->original_file_name)
                ->delete();

            if ($campaignReferences->isNotEmpty()) {
                ClientSource::query()
                    ->where('channel', 'ivr')
                    ->where('source_type', 'campaign_result')
                    ->whereIn('source_reference', $campaignReferences)
                    ->delete();

                $suppressionQuery = ContactSuppression::query()
                    ->where('channel', 'ivr')
                    ->where('reason', 'customer_unsubscribed');

                $suppressionQuery->where(function ($q) use ($campaignReferences): void {
                    foreach ($campaignReferences as $ref) {
                        $q->orWhereJsonContains('context->campaign_id', $ref);
                    }
                })->delete();
            }

            $import->callRecords()->delete();

            IvrCampaign::query()
                ->whereIn('id', $campaignIds)
                ->doesntHave('callRecords')
                ->delete();

            if ($campaignReferences->isNotEmpty()) {
                IvrCampaign::query()
                    ->whereIn('external_campaign_id', $campaignReferences)
                    ->doesntHave('callRecords')
                    ->delete();
            }

            $import->update([
                'status' => 'reverted',
                'reverted_at' => now(),
                'reverted_by' => $request->user()?->id,
                'revert_reason' => $validated['revert_reason'] ?? null,
            ]);
        });

        $this->cleanupAffectedPhoneNumbers($affectedPhoneIds, $eligibilityService);

        Log::channel('ivr')->info('Reverted IVR campaign results import.', [
            'import_id' => $import->id,
            'file_name' => $import->original_file_name,
            'phone_numbers_checked' => $affectedPhoneIds->count(),
        ]);

        return redirect()
            ->route('modules.ivr.results.index')
            ->with('status', "Campaign import {$import->original_file_name} was reverted.");
    }

    private function cleanupAffectedPhoneNumbers($phoneIds, NumberEligibilityService $eligibilityService): void
    {
        ClientPhoneNumber::query()
            ->whereIn('id', $phoneIds)
            ->with(['client', 'sources', 'suppressions', 'ivrCallRecords'])
            ->each(function (ClientPhoneNumber $phoneNumber) use ($eligibilityService): void {
                $hasActiveIvrSuppression = $phoneNumber->suppressions
                    ->where('channel', 'ivr')
                    ->whereNull('released_at')
                    ->isNotEmpty();

                if (! $hasActiveIvrSuppression && $phoneNumber->unsubscribed_at !== null) {
                    $phoneNumber->forceFill(['unsubscribed_at' => null])->save();
                }

                if ($phoneNumber->sources->isEmpty() && $phoneNumber->suppressions->isEmpty() && $phoneNumber->ivrCallRecords->isEmpty()) {
                    $client = $phoneNumber->client;

                    $phoneNumber->delete();

                    if ($client && $client->phoneNumbers()->doesntExist() && $client->sources()->doesntExist()) {
                        $client->delete();
                    }

                    return;
                }

                $eligibilityService->refresh($phoneNumber->refresh());
            });
    }

    /**
     * @return array{
     *     total_calls: int,
     *     answered_calls: int,
     *     missed_calls: int,
     *     leads_count: int,
     *     more_info_count: int,
     *     unsubscribed_count: int,
     *     time_consumed_minutes: int
     * }
     */
    private function campaignStats(IvrCampaign $campaign): array
    {
        $base = $campaign->callRecords();

        $answeredSecondsList = (clone $base)
            ->whereRaw('lower(call_status) = ?', ['answered'])
            ->pluck('total_duration_seconds');

        return [
            'total_calls' => (clone $base)->count(),
            'answered_calls' => $answeredSecondsList->count(),
            'missed_calls' => (clone $base)->whereRaw('lower(call_status) = ?', ['missed'])->count(),
            'leads_count' => (clone $base)->where('dtmf_outcome', 'interested')->count(),
            'more_info_count' => (clone $base)->where('dtmf_outcome', 'more_info')->count(),
            'unsubscribed_count' => (clone $base)->where('dtmf_outcome', 'unsubscribe')->count(),
            'time_consumed_minutes' => $answeredSecondsList->sum(fn ($s): int => BillableDuration::minutes((int) $s)),
        ];
    }
}
