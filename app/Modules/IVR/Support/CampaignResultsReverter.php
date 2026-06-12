<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\ContactSuppression;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Enums\IvrImportType;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\IvrSummaryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CampaignResultsReverter
{
    public function revert(
        IvrImport $import,
        ?int $userId = null,
        ?string $reason = null,
        ?NumberEligibilityService $eligibilityService = null,
    ): void {
        if ($import->type !== IvrImportType::CampaignResults->value) {
            abort(404);
        }

        if (in_array($import->status, [IvrImportStatus::Pending->value, IvrImportStatus::Processing->value], true)) {
            return;
        }

        if ($import->reverted_at !== null) {
            return;
        }

        $eligibilityService ??= app(NumberEligibilityService::class);

        $affectedPhoneIds  = collect();
        $affectedMonths    = collect();

        $callRecordsForAudio = $import->callRecords()->whereNotNull('ivr_campaign_id')->get(['ivr_campaign_id', 'ivr_import_id']);
        $importCampaignIds = $callRecordsForAudio->pluck('ivr_campaign_id')->unique();

        $audioPathsToDelete = IvrCampaign::query()
            ->whereIn('id', $importCampaignIds)
            ->whereNotNull('audio_file_path')
            ->whereDoesntHave('callRecords', fn ($q) => $q->where('ivr_import_id', '!=', $import->id))
            ->pluck('audio_file_path');

        DB::transaction(function () use ($import, $userId, $reason, &$affectedPhoneIds, &$affectedMonths): void {
            /** @var EloquentCollection<int, IvrCallRecord> $callRecords */
            $callRecords = $import->callRecords()->with('campaign')->get();

            $affectedPhoneIds = $callRecords
                ->pluck('client_phone_number_id')
                ->filter()
                ->unique()
                ->values();

            // Capture year/month pairs before records are deleted so we can refresh
            // the monthly summaries after the transaction completes.
            $affectedMonths = $callRecords
                ->filter(fn (IvrCallRecord $r) => $r->call_time !== null)
                ->map(fn (IvrCallRecord $r) => \Carbon\Carbon::parse($r->call_time)->format('Y-n'))
                ->unique()
                ->map(function (string $ym): array {
                    [$year, $month] = explode('-', $ym);
                    return ['year' => (int) $year, 'month' => (int) $month];
                })
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
                'status' => IvrImportStatus::Reverted,
                'reverted_at' => now(),
                'reverted_by' => $userId,
                'revert_reason' => $reason,
            ]);
        });

        foreach ($audioPathsToDelete as $audioPath) {
            Storage::disk('local')->delete($audioPath);
        }

        $this->cleanupAffectedPhoneNumbers($affectedPhoneIds, $eligibilityService);

        $summaryService = app(IvrSummaryService::class);
        foreach ($affectedMonths as $m) {
            $summaryService->recompute($m['year'], $m['month']);
        }

        Log::channel('ivr')->info('Reverted IVR campaign results import.', [
            'import_id' => $import->id,
            'file_name' => $import->original_file_name,
            'phone_numbers_checked' => $affectedPhoneIds->count(),
        ]);
    }

    private function cleanupAffectedPhoneNumbers(Collection $phoneIds, NumberEligibilityService $eligibilityService): void
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
}
