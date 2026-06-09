<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Jobs\BatchAnalyseWhatsAppNumbers;
use App\Modules\WhatsApp\Models\WhatsAppCampaign;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use SplFileObject;
use Throwable;

class WhatsAppCampaignResultsProcessor
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly WhatsAppPhoneNormalizer $phoneNormalizer,
        private readonly WhatsAppSummaryService $summaryService,
    ) {}

    public function process(WhatsAppImport $import): void
    {
        ini_set('memory_limit', '512M');

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $import->update([
            'status' => WhatsAppImportStatus::Processing->value,
            'started_at' => now(),
            'error_message' => null,
        ]);

        Log::channel('whatsapp')->info('Starting WhatsApp campaign results import.', ['import_id' => $import->id]);

        try {
            $file = new SplFileObject(storage_path('app/private/'.$import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $totalRows = $this->countDataRows($file);
            $import->update(['total_rows' => $totalRows]);

            $file->rewind();

            $header = null;
            $processed = 0;
            $successful = 0;
            $failed = 0;
            $rowNumber = 0;

            // Keyed by campaign name → WhatsAppCampaign
            $campaignsByName = [];

            // Keyed by normalized phone → client_phone_number_id
            $phoneIdCache = [];

            // Pending rows to bulk-insert into whatsapp_messages
            $messageBuffer = [];

            $now = now()->toDateTimeString();

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                if ($header === null) {
                    $header = $row;
                    continue;
                }

                $processed++;

                try {
                    $payload = $this->mapRow($header, $row);

                    // Resolve campaign (cached per name)
                    $campaignName = (string) $payload['CampaignName'];

                    if (! isset($campaignsByName[$campaignName])) {
                        $campaignsByName[$campaignName] = $this->findOrCreateCampaign($payload);
                    }

                    $campaign = $campaignsByName[$campaignName];

                    // Resolve phone number (cached per normalized phone)
                    $normalized = $this->phoneNormalizer->normalize((string) $payload['PhoneNumber']);
                    $normalizedPhone = $normalized['normalized'];

                    if (! isset($phoneIdCache[$normalizedPhone])) {
                        $phoneIdCache[$normalizedPhone] = $this->resolvePhoneNumberId(
                            $normalizedPhone,
                            $normalized,
                            $payload,
                            $campaign,
                            $import,
                        );
                    }

                    $messageBuffer[] = $this->buildMessageRow(
                        $campaign->id,
                        $import->id,
                        $phoneIdCache[$normalizedPhone],
                        $payload,
                        $now,
                    );

                    $successful++;
                } catch (Throwable $throwable) {
                    $failed++;

                    $import->errors()->create([
                        'row_number'    => $rowNumber,
                        'error_type'    => 'row_validation',
                        'error_message' => $throwable->getMessage(),
                        'row_payload'   => $row ?? null,
                    ]);

                    Log::channel('whatsapp')->warning('Campaign results row failed.', [
                        'import_id' => $import->id,
                        'row_number' => $rowNumber,
                        'message' => $throwable->getMessage(),
                    ]);
                }

                if (count($messageBuffer) >= self::BATCH_SIZE) {
                    DB::table('whatsapp_messages')->insert($messageBuffer);
                    $messageBuffer = [];

                    $import->update([
                        'processed_rows' => $processed,
                        'successful_rows' => $successful,
                        'failed_rows' => $failed,
                    ]);
                }
            }

            // Flush remaining rows
            if ($messageBuffer !== []) {
                DB::table('whatsapp_messages')->insert($messageBuffer);
            }

            foreach ($campaignsByName as $campaign) {
                $this->refreshCampaignMetrics($campaign);

                if ($campaign->started_at !== null) {
                    $this->summaryService->recompute(
                        $campaign->started_at->year,
                        $campaign->started_at->month,
                    );
                }
            }

            // One batch job covers all unique numbers from this import.
            // Runs on the 'analysis' queue so it never blocks the next import.
            BatchAnalyseWhatsAppNumbers::dispatch(array_values($phoneIdCache))
                ->onQueue('analysis');

            $import->update([
                'status' => $failed > 0
                    ? WhatsAppImportStatus::CompletedWithErrors->value
                    : WhatsAppImportStatus::Completed->value,
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'duplicate_rows' => 0,
                'completed_at' => now(),
            ]);

            Log::channel('whatsapp')->info('Completed WhatsApp campaign results import.', ['import_id' => $import->id]);
        } catch (Throwable $throwable) {
            $import->update([
                'status' => WhatsAppImportStatus::Failed->value,
                'error_message' => $throwable->getMessage(),
                'completed_at' => now(),
            ]);

            Log::channel('whatsapp')->error('Campaign results import failed.', [
                'import_id' => $import->id,
                'message' => $throwable->getMessage(),
            ]);
        } finally {
            if (class_exists(Telescope::class)) {
                Telescope::startRecording();
            }
        }
    }

    private function countDataRows(SplFileObject $file): int
    {
        $count = 0;
        $headerSeen = false;

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            if (! $headerSeen) {
                $headerSeen = true;
                continue;
            }

            $count++;
        }

        return $count;
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

        if (empty($mapped['PhoneNumber'])) {
            throw new \RuntimeException('PhoneNumber is required.');
        }

        if (empty($mapped['CampaignName'])) {
            throw new \RuntimeException('CampaignName is required.');
        }

        return $mapped;
    }

    /**
     * @param  array<string, string|null>  $payload
     */
    private function findOrCreateCampaign(array $payload): WhatsAppCampaign
    {
        return WhatsAppCampaign::firstOrCreate(
            ['name' => (string) $payload['CampaignName']],
            ['started_at' => $this->parseScheduledAt($payload['ScheduleAt'] ?? null)],
        );
    }

    /**
     * Look up an existing ClientPhoneNumber by its normalized phone, or create a new
     * Client + ClientPhoneNumber + ClientSource if this is the first time we've seen it.
     *
     * @param  array{normalized:string, country_code:?string, national_number:?string, detected_country:?string, is_uae:bool}  $normalized
     * @param  array<string, string|null>  $payload
     */
    private function resolvePhoneNumberId(
        string $normalizedPhone,
        array $normalized,
        array $payload,
        WhatsAppCampaign $campaign,
        WhatsAppImport $import,
    ): int {
        $phoneNumber = ClientPhoneNumber::query()
            ->where('normalized_phone', $normalizedPhone)
            ->value('id');

        if ($phoneNumber !== null) {
            return $phoneNumber;
        }

        $client = Client::create();

        $phoneNumber = ClientPhoneNumber::create([
            'client_id' => $client->id,
            'raw_phone' => $payload['PhoneNumber'],
            'normalized_phone' => $normalized['normalized'],
            'country_code' => $normalized['country_code'],
            'national_number' => $normalized['national_number'],
            'detected_country' => $normalized['detected_country'],
            'is_uae' => $normalized['is_uae'],
            'is_whatsapp' => true,
        ]);

        ClientSource::create([
            'client_id' => $phoneNumber->client_id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel' => 'whatsapp',
            'source_type' => 'campaign_result',
            'source_name' => $campaign->name,
            'source_file_name' => $import->original_file_name,
            'source_reference' => $campaign->name,
            'metadata' => [
                'delivery_status' => $payload['Status'] ?? null,
                'template_name' => $payload['TemplateName'] ?? null,
            ],
        ]);

        return $phoneNumber->id;
    }

    /**
     * Build a raw row array suitable for a bulk DB insert into whatsapp_messages.
     *
     * @param  array<string, string|null>  $payload
     * @return array<string, mixed>
     */
    private function buildMessageRow(
        int $campaignId,
        int $importId,
        int $phoneNumberId,
        array $payload,
        string $now,
    ): array {
        return [
            'whatsapp_campaign_id' => $campaignId,
            'whatsapp_import_id' => $importId,
            'client_phone_number_id' => $phoneNumberId,
            'scheduled_at' => $this->parseScheduledAt($payload['ScheduleAt'] ?? null)?->toDateTimeString(),
            'template_name' => $payload['TemplateName'] ?? null,
            'delivery_status' => $payload['Status'] ?? null,
            'failure_reason' => ($payload['Failure reason'] ?? '') !== '' ? $payload['Failure reason'] : null,
            'has_quick_replies' => $this->parseBool($payload['Quick replies'] ?? null) ? 1 : 0,
            'quick_reply_1' => ($payload['Quick reply 1'] ?? '') !== '' ? $payload['Quick reply 1'] : null,
            'quick_reply_2' => ($payload['Quick reply 2'] ?? '') !== '' ? $payload['Quick reply 2'] : null,
            'quick_reply_3' => ($payload['Quick reply 3'] ?? '') !== '' ? $payload['Quick reply 3'] : null,
            'clicked' => $this->parseBool($payload['Clicked'] ?? null) ? 1 : 0,
            'retried' => $this->parseBool($payload['Retried'] ?? null) ? 1 : 0,
            'raw_payload' => json_encode($payload),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function refreshCampaignMetrics(WhatsAppCampaign $campaign): void
    {
        $row = $campaign->messages()
            ->selectRaw('count(*) as total_messages')
            ->selectRaw("sum(case when delivery_status = 'SENT'      then 1 else 0 end) as sent_count")
            ->selectRaw("sum(case when delivery_status = 'DELIVERED' then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when delivery_status = 'READ'      then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when delivery_status = 'REPLIED'   then 1 else 0 end) as replied_count")
            ->selectRaw("sum(case when delivery_status = 'FAILED'    then 1 else 0 end) as failed_count")
            ->first();

        $campaign->forceFill([
            'total_messages'  => (int) ($row->total_messages ?? 0),
            'sent_count'      => (int) ($row->sent_count ?? 0),
            'delivered_count' => (int) ($row->delivered_count ?? 0),
            'read_count'      => (int) ($row->read_count ?? 0),
            'replied_count'   => (int) ($row->replied_count ?? 0),
            'failed_count'    => (int) ($row->failed_count ?? 0),
        ])->save();
    }

    private function parseScheduledAt(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('m/d/Y H:i', $value);
        } catch (Throwable) {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }
    }

    private function parseBool(?string $value): bool
    {
        return strtoupper(trim((string) $value)) === 'TRUE';
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
