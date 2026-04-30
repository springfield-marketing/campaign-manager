<?php

namespace App\Modules\IVR\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\ContactSuppression;
use App\Modules\IVR\Models\IvrCallRecord;
use App\Modules\IVR\Models\IvrCampaign;
use App\Modules\IVR\Models\IvrImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

class CampaignResultsProcessor
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly NumberEligibilityService $eligibilityService,
    ) {
    }

    public function process(IvrImport $import): void
    {
        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        Log::channel('ivr')->info('Starting IVR campaign results import.', ['import_id' => $import->id]);

        try {
            $file = new SplFileObject(storage_path('app/private/'.$import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $inspection = $this->inspectFile($file);
            $this->ensureCampaignHasNotBeenImported($inspection['summary'], $import);

            $import->update(['total_rows' => $inspection['total_rows']]);

            $file->rewind();

            $summary = [];
            $header = null;
            $headerRowNumber = 0;
            $processed = 0;
            $successful = 0;
            $failed = 0;
            $duplicates = 0;
            $rowNumber = 0;

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $firstCell = trim((string) ($row[0] ?? ''));

                if ($header === null) {
                    if ($firstCell === 'Campaign Summary' || $firstCell === 'Call Details Records') {
                        continue;
                    }

                    if ($firstCell === 'Call UUID') {
                        $header = $row;
                        $headerRowNumber = $rowNumber;
                        continue;
                    }

                    if (count($row) >= 2) {
                        $summary[trim((string) $row[0])] = trim((string) $row[1]);
                    }

                    continue;
                }

                $processed++;

                try {
                    $payload = $this->mapRow($header, $row);
                    $campaign = $this->upsertCampaign($summary, $payload, $import);
                    $duplicate = $this->upsertCallRecord($campaign, $payload, $import);

                    $successful++;
                    $duplicates += $duplicate ? 1 : 0;
                } catch (Throwable $throwable) {
                    $failed++;

                    $import->errors()->create([
                        'row_number' => $rowNumber,
                        'error_type' => 'campaign_row_validation',
                        'error_message' => $throwable->getMessage(),
                        'row_payload' => $row,
                    ]);

                    Log::channel('ivr')->warning('Campaign results row failed.', [
                        'import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'message' => $throwable->getMessage(),
                    ]);
                }

                if ($processed % 250 === 0) {
                    $import->update([
                        'processed_rows' => $processed,
                        'successful_rows' => $successful,
                        'failed_rows' => $failed,
                        'duplicate_rows' => $duplicates,
                    ]);
                }
            }

            $campaignId = $summary['order_number'] ?? null;
            $campaign = $campaignId ? IvrCampaign::query()->where('external_campaign_id', $campaignId)->first() : null;

            if ($campaign) {
                $this->refreshCampaignMetrics($campaign);
            }

            $import->update([
                'status' => $failed > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'duplicate_rows' => $duplicates,
                'completed_at' => now(),
                'summary' => array_merge($summary, [
                    'header_row' => $headerRowNumber,
                ]),
            ]);

            Log::channel('ivr')->info('Completed IVR campaign results import.', ['import_id' => $import->id]);
        } catch (Throwable $throwable) {
            $import->update([
                'status' => 'failed',
                'error_message' => $throwable->getMessage(),
                'completed_at' => now(),
            ]);

            Log::channel('ivr')->error('Campaign results import failed.', [
                'import_id' => $import->id,
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function mapRow(array $header, array $row): array
    {
        $mapped = [];

        foreach ($header as $index => $column) {
            $mapped[trim((string) $column)] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        if (($mapped['Call UUID'] ?? null) === null || $mapped['Call UUID'] === '') {
            throw new \RuntimeException('Call UUID is required.');
        }

        if (($mapped['Customer'] ?? null) === null || $mapped['Customer'] === '') {
            throw new \RuntimeException('Customer phone number is required.');
        }

        return $mapped;
    }

    private function upsertCampaign(array $summary, array $payload, IvrImport $import): IvrCampaign
    {
        $campaignId = (string) ($summary['order_number'] ?? $payload['Campaign ID'] ?? pathinfo($import->original_file_name, PATHINFO_FILENAME));

        return IvrCampaign::query()->updateOrCreate(
            ['external_campaign_id' => $campaignId],
            [
                'name' => $campaignId,
                'platform' => $payload['Solution'] ?? 'IVR',
                'state' => $summary['order_state'] ?? null,
                'total_calls' => (int) ($summary['total_calls'] ?? 0),
                'answered_calls' => (int) ($summary['answered_calls'] ?? 0),
                'unanswered_calls' => (int) ($summary['unanswered_calls'] ?? 0),
                'started_at' => $this->parseSummaryDate($summary['start_time'] ?? null),
                'completed_at' => $this->parseSummaryDate($summary['stop_time'] ?? null),
                'summary' => $summary,
            ],
        );
    }

    private function ensureCampaignHasNotBeenImported(array $summary, IvrImport $import): void
    {
        $campaignId = (string) ($summary['order_number'] ?? pathinfo($import->original_file_name, PATHINFO_FILENAME));

        if (IvrCampaign::query()->where('external_campaign_id', $campaignId)->exists()) {
            throw new \RuntimeException("Campaign {$campaignId} has already been imported.");
        }
    }

    /**
     * @return array{summary: array<string, string>, total_rows: int}
     */
    private function inspectFile(SplFileObject $file): array
    {
        $summary = [];
        $hasReachedDetails = false;
        $totalRows = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            $firstCell = trim((string) ($row[0] ?? ''));

            if (! $hasReachedDetails) {
                if ($firstCell === 'Campaign Summary' || $firstCell === 'Call Details Records') {
                    continue;
                }

                if ($firstCell === 'Call UUID') {
                    $hasReachedDetails = true;
                    continue;
                }

                if (count($row) >= 2) {
                    $summary[trim((string) $row[0])] = trim((string) $row[1]);
                }

                continue;
            }

            $totalRows++;
        }

        return [
            'summary' => $summary,
            'total_rows' => $totalRows,
        ];
    }

    private function upsertCallRecord(IvrCampaign $campaign, array $payload, IvrImport $import): bool
    {
        $normalized = $this->phoneNormalizer->normalize((string) $payload['Customer']);
        $phoneNumber = ClientPhoneNumber::query()->where('normalized_phone', $normalized['normalized'])->first();

        if (! $phoneNumber) {
            $client = Client::create();

            $phoneNumber = ClientPhoneNumber::create([
                'client_id' => $client->id,
                'raw_phone' => $payload['Customer'],
                'normalized_phone' => $normalized['normalized'],
                'country_code' => $normalized['country_code'],
                'national_number' => $normalized['national_number'],
                'detected_country' => $normalized['detected_country'],
                'is_uae' => $normalized['is_uae'],
            ]);
        }

        $dtmfExtensions = $this->parseDtmfExtensions($payload['DTMF Extensions'] ?? null);
        $dtmfOutcome = $this->resolveDtmfOutcome($dtmfExtensions);
        $callTime = $this->parseCallTime($payload['Date and Time'] ?? null);

        $callRecord = IvrCallRecord::query()->where('external_call_uuid', (string) $payload['Call UUID'])->first();
        $duplicate = $callRecord !== null;

        $callRecord = IvrCallRecord::query()->updateOrCreate(
            ['external_call_uuid' => (string) $payload['Call UUID']],
            [
                'ivr_campaign_id' => $campaign->id,
                'ivr_import_id' => $import->id,
                'client_phone_number_id' => $phoneNumber->id,
                'call_time' => $callTime,
                'call_direction' => $payload['Call Direction'] ?? null,
                'call_status' => $payload['Call Status'] ?? null,
                'customer_status' => $payload['Customer Status'] ?? null,
                'agent_status' => $payload['Agent Status'] ?? null,
                'total_duration_seconds' => $this->durationToSeconds($payload['Total Call Duration (hh:mm:ss)'] ?? null),
                'talk_time_seconds' => $this->durationToSeconds($payload['Talk Time (hh:mm:ss)'] ?? null),
                'call_action' => $payload['Call Actions'] ?? null,
                'dtmf_extensions' => $dtmfExtensions,
                'dtmf_outcome' => $dtmfOutcome,
                'queue' => $payload['Queue'] ?? null,
                'disposition' => $payload['Disposition'] ?? null,
                'sub_disposition' => $payload['Sub Disposition'] ?? null,
                'hangup_by' => $payload['Hangup By'] ?? null,
                'ivr_id' => $payload['IVR ID'] ?? null,
                'credits_deducted' => is_numeric($payload['Credits Deducted'] ?? null) ? (float) $payload['Credits Deducted'] : null,
                'follow_up_date' => $this->parseCallTime($payload['Follow Up Date'] ?? null),
                'raw_payload' => $payload,
            ],
        );

        if (! $duplicate) {
            ClientSource::create([
                'client_id' => $phoneNumber->client_id,
                'client_phone_number_id' => $phoneNumber->id,
                'channel' => 'ivr',
                'source_type' => 'campaign_result',
                'source_name' => $campaign->name,
                'source_file_name' => $import->original_file_name,
                'source_reference' => $campaign->external_campaign_id,
                'metadata' => [
                    'call_uuid' => $callRecord->external_call_uuid,
                    'call_status' => $callRecord->call_status,
                ],
            ]);
        }

        if ($dtmfOutcome === 'unsubscribe') {
            ContactSuppression::query()->firstOrCreate(
                [
                    'client_phone_number_id' => $phoneNumber->id,
                    'channel' => 'ivr',
                    'reason' => 'customer_unsubscribed',
                ],
                [
                    'context' => ['campaign_id' => $campaign->external_campaign_id],
                    'suppressed_at' => $callTime ?: now(),
                ],
            );

            $phoneNumber->forceFill(['unsubscribed_at' => $callTime ?: now()])->save();
        }

        $phoneNumber->forceFill([
            'last_called_at' => $callTime,
            'last_call_outcome' => $dtmfOutcome,
        ])->save();

        $this->eligibilityService->refresh($phoneNumber);

        return $duplicate;
    }

    private function refreshCampaignMetrics(IvrCampaign $campaign): void
    {
        $campaign->forceFill([
            'leads_count' => $campaign->callRecords()->where('dtmf_outcome', 'interested')->count(),
            'more_info_count' => $campaign->callRecords()->where('dtmf_outcome', 'more_info')->count(),
            'unsubscribed_count' => $campaign->callRecords()->where('dtmf_outcome', 'unsubscribe')->count(),
            'credits_used' => (float) $campaign->callRecords()->sum('credits_deducted'),
        ])->save();
    }

    /**
     * @return array<int, string>
     */
    private function parseDtmfExtensions(?string $raw): array
    {
        if ($raw === null || trim($raw) === '' || trim($raw) === '[]') {
            return [];
        }

        preg_match_all("/[A-Za-z0-9_]+/", $raw, $matches);

        return array_values(array_filter($matches[0] ?? []));
    }

    /**
     * @param  array<int, string>  $extensions
     */
    private function resolveDtmfOutcome(array $extensions): string
    {
        $normalized = array_map('strtoupper', $extensions);

        foreach (config('ivr.dtmf.interested', []) as $value) {
            if (in_array(strtoupper($value), $normalized, true)) {
                return 'interested';
            }
        }

        foreach (config('ivr.dtmf.more_info', []) as $value) {
            if (in_array(strtoupper($value), $normalized, true)) {
                return 'more_info';
            }
        }

        foreach (config('ivr.dtmf.unsubscribe', []) as $value) {
            if (in_array(strtoupper($value), $normalized, true)) {
                return 'unsubscribe';
            }
        }

        if ($normalized === [] || collect($normalized)->contains(fn ($value) => str_contains($value, 'UNDEF'))) {
            return 'no_input';
        }

        return 'other';
    }

    private function durationToSeconds(?string $value): int
    {
        if ($value === null || trim($value) === '') {
            return 0;
        }

        $parts = array_map('intval', explode(':', $value));

        if (count($parts) !== 3) {
            return 0;
        }

        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }

    private function parseSummaryDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::createFromFormat('d-m-Y H:i', $value) ?: null;
    }

    private function parseCallTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
