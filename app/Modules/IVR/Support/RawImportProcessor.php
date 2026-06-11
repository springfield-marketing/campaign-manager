<?php

namespace App\Modules\IVR\Support;

use App\Jobs\RecomputeClientScoresJob;
use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Models\ImportStaging;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Models\IvrImport;
use App\Support\LocationResolver;
use App\Support\NameNormalizer;
use App\Support\RawContactImportEnricher;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use SplFileObject;
use Throwable;

class RawImportProcessor
{
    use CsvRowTrait;

    private const CHUNK_SIZE = 1000;

    private const SOURCE_INSERT_CHUNK = 1000;

    private const RELATIONSHIP_TYPES = [
        'owner', 'resident', 'tenant', 'buyer_interest',
        'seller_interest', 'investor', 'past_owner', 'unknown',
    ];

    private const CONFIDENCE_LEVELS = ['high', 'medium', 'low'];

    /** Canonical emirate names. */
    private const VALID_EMIRATES = [
        'Abu Dhabi', 'Dubai', 'Sharjah',
        'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah',
    ];

    /**
     * Lowercase aliases → canonical emirate name.
     * Covers common district/community names that sometimes appear in CSV
     * "City" or "Emirate" columns instead of the actual emirate name.
     *
     * @var array<string, string>
     */
    private const EMIRATE_ALIASES = [
        // Abu Dhabi
        'abu dhabi'          => 'Abu Dhabi',
        'abu dhabi district' => 'Abu Dhabi',
        'abu dhabi city'     => 'Abu Dhabi',
        'abudhabi'           => 'Abu Dhabi',
        'ad'                 => 'Abu Dhabi',
        'ras al hekma'       => 'Abu Dhabi',
        'ras al hikma'       => 'Abu Dhabi',
        // Dubai
        'dubai'              => 'Dubai',
        'dxb'                => 'Dubai',
        'dubailand district' => 'Dubai',
        'downtown district'  => 'Dubai',
        'downtown dubai'     => 'Dubai',
        'al barsha south'    => 'Dubai',
        'meydan district'    => 'Dubai',
        'creek district'     => 'Dubai',
        'dubai creek'        => 'Dubai',
        'dubai marina'       => 'Dubai',
        'warsan first'       => 'Dubai',
        'bur dubai district' => 'Dubai',
        'bur dubai'          => 'Dubai',
        'deira district'     => 'Dubai',
        'deira'              => 'Dubai',
        // Sharjah
        'sharjah'            => 'Sharjah',
        // Ajman
        'ajman'              => 'Ajman',
        // Umm Al Quwain
        'umm al quwain'      => 'Umm Al Quwain',
        'umm al-quwain'      => 'Umm Al Quwain',
        'uaq'                => 'Umm Al Quwain',
        // Ras Al Khaimah
        'ras al khaimah'     => 'Ras Al Khaimah',
        'ras al-khaimah'     => 'Ras Al Khaimah',
        'rak'                => 'Ras Al Khaimah',
        // Fujairah
        'fujairah'           => 'Fujairah',
    ];

    private LocationResolver $resolver;
    private RawContactImportEnricher $enricher;

    /** @var array<string, Tag> Cached tags keyed by name to avoid a query per row */
    private array $tagCache = [];

    /** @var array<int, array<string, mixed>> Buffered ClientSource rows for bulk insert */
    private array $sourceBuffer = [];

    /** @var array<string, true> Normalized phones seen earlier in this import */
    private array $seenPhones = [];

    public function __construct(
        private readonly RawImportColumnMapper $mapper,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
        $this->resolver = new LocationResolver();
        $this->enricher = new RawContactImportEnricher();
    }

    public function process(IvrImport $import): void
    {
        ini_set('memory_limit', '1024M');

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $batchId = 'raw-import-' . $import->id;

        // Purge any data from a previous run so reprocessing is idempotent
        DB::table('client_sources')
            ->where('source_reference', (string) $import->id)
            ->where('channel', 'ivr')
            ->where('source_type', 'raw_import')
            ->delete();
        DB::table('import_staging')
            ->where('batch_id', $batchId)
            ->delete();

        $import->update([
            'status' => IvrImportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);
        $import->broadcastProgress();

        Log::channel('ivr')->info('Starting raw IVR import.', ['import_id' => $import->id]);

        try {
            $file = new SplFileObject(storage_path('app/private/'.$import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $header = $this->readHeader($file);
            $mapping = $this->mapper->map($header);

            if ($mapping['missing'] !== []) {
                throw new \RuntimeException('Missing required columns: '.implode(', ', $mapping['missing']));
            }

            $import->update(['total_rows' => $this->countDataRows($file)]);
            $import->broadcastProgress();
            $file->rewind();
            $this->readHeader($file);

            $processed = 0;
            $successful = 0;
            $failed = 0;
            $duplicates = 0;
            $staged = 0;
            $rowNumber = 1;
            $sourceFallback = $import->source_name ?: pathinfo($import->original_file_name, PATHINFO_FILENAME);
            $this->tagCache = [];
            $this->sourceBuffer = [];
            $this->seenPhones = [];
            $chunk = [];

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || ($row === [null] && $file->eof())) break;
                if ($this->rowIsEmpty($row)) continue;

                $chunk[] = [
                    'row_number' => $rowNumber,
                    'row' => $row,
                ];

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $result = $this->processChunk($chunk, $mapping['mapped'], $sourceFallback, $batchId, $import);
                    $chunk = [];

                    $processed += $result['processed'];
                    $successful += $result['successful'];
                    $failed += $result['failed'];
                    $duplicates += $result['duplicates'];
                    $staged += $result['staged'];

                    $import->update([
                        'processed_rows' => $processed,
                        'successful_rows' => $successful,
                        'failed_rows' => $failed,
                        'duplicate_rows' => $duplicates,
                    ]);
                    $import->broadcastProgress();
                }
            }

            if ($chunk !== []) {
                $result = $this->processChunk($chunk, $mapping['mapped'], $sourceFallback, $batchId, $import);

                $processed += $result['processed'];
                $successful += $result['successful'];
                $failed += $result['failed'];
                $duplicates += $result['duplicates'];
                $staged += $result['staged'];
            }

            $import->update([
                'status' => $failed > 0 ? IvrImportStatus::CompletedWithErrors : IvrImportStatus::Completed,
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'successful_rows' => $successful,
                'failed_rows' => $failed,
                'duplicate_rows' => $duplicates,
                'completed_at' => now(),
                'summary' => [
                    'required_columns' => config('ivr.raw_import.required'),
                    'mapped_columns' => array_keys($mapping['mapped']),
                    'staged_rows' => $staged,
                    'staging_batch_id' => $staged > 0 ? $batchId : null,
                ],
            ]);
            $import->broadcastProgress();

            // Rescore all clients touched by this import
            $affectedClientIds = DB::table('client_sources')
                ->where('source_reference', (string) $import->id)
                ->where('channel', 'ivr')
                ->where('source_type', 'raw_import')
                ->pluck('client_id')
                ->unique()
                ->all();

            if ($affectedClientIds !== []) {
                RecomputeClientScoresJob::dispatch($affectedClientIds)->onQueue('analysis');
            }

            Log::channel('ivr')->info('Completed raw IVR import.', ['import_id' => $import->id]);
        } catch (Throwable $throwable) {
            $import->update([
                'status' => IvrImportStatus::Failed,
                'error_message' => $throwable->getMessage(),
                'completed_at' => now(),
            ]);
            $import->broadcastProgress();

            Log::channel('ivr')->error('Raw IVR import failed.', [
                'import_id' => $import->id,
                'message' => $throwable->getMessage(),
            ]);
        } finally {
            if (class_exists(Telescope::class)) {
                Telescope::startRecording();
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function readHeader(SplFileObject $file): array
    {
        while (! $file->eof()) {
            $header = $file->fgetcsv();

            if (! is_array($header) || ($header === [null] && $file->eof())) break;

            if (! $this->rowIsEmpty($header)) {
                $header = array_map(fn ($value) => (string) $value, $header);
                // Strip UTF-8 BOM that Excel adds to the first cell
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

                return $header;
            }
        }

        throw new \RuntimeException('Import file is empty.');
    }

    private function countDataRows(SplFileObject $file): int
    {
        $count = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();

            if (! is_array($row) || ($row === [null] && $file->eof())) break;
            if (! $this->rowIsEmpty($row)) $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $mapping
     * @return array<string, string|null>
     */
    private function extractPayload(array $row, array $mapping): array
    {
        $payload = [];

        foreach ($mapping as $column => $index) {
            $payload[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        if (($payload['name'] ?? null) === null || ($payload['name'] ?? '') === '') {
            throw new \RuntimeException('Name is required.');
        }

        $payload['name'] = NameNormalizer::normalize($payload['name']);

        return $payload;
    }

    private function stageForReview(array $payload, string $batchId, string $sourceName): void
    {
        $emirate = $this->normalizeEmirate($payload['emirate'] ?? '');
        $officialAreaId  = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
        $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
        $projectId       = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
        $buildingId      = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');

        ImportStaging::create([
            'batch_id'           => $batchId,
            'name'               => $payload['name'] ?? null,
            'phone'              => null,
            'email'              => null,
            'country_iso'        => $payload['country_iso'] ?? null,
            'emirate'            => $emirate ?: null,
            'raw_official_area'  => $payload['official_area_name'] ?? null,
            'raw_marketing_area' => $payload['marketing_area_name'] ?? null,
            'raw_project_name'   => $payload['project_name'] ?? null,
            'raw_building_name'  => $payload['building_name'] ?? null,
            'raw_unit_reference' => $payload['unit_reference'] ?? null,
            'official_area_id'   => $officialAreaId,
            'marketing_area_id'  => $marketingAreaId,
            'project_id'         => $projectId,
            'building_id'        => $buildingId,
            'relationship_type'  => $payload['relationship_type'] ?? null,
            'confidence_level'   => $payload['confidence_level'] ?? null,
            'source'             => $sourceName,
            'status'             => ImportStaging::STATUS_NEEDS_REVIEW,
            'status_reason'      => 'Name only — no phone or email in source data.',
        ]);
    }

    /**
     * @param  array<int, array{row_number:int, row:array<int, string|null>}>  $rows
     * @param  array<string, int>  $mapping
     * @return array{processed:int, successful:int, failed:int, duplicates:int, staged:int}
     */
    private function processChunk(array $rows, array $mapping, string $sourceFallback, string $batchId, IvrImport $import): array
    {
        $items = [];
        $errors = [];
        $stagingRows = [];
        $processed = count($rows);
        $duplicates = 0;
        $staged = 0;

        foreach ($rows as $entry) {
            try {
                $payload = $this->extractPayload($entry['row'], $mapping);
                $sourceName = ($payload['source'] ?? null) ?: ($payload['source_file'] ?? null) ?: $sourceFallback;

                $hasPhone = ($payload['phone'] ?? '') !== '';
                $hasEmail = ($payload['email'] ?? '') !== '';

                if (! $hasPhone && ! $hasEmail) {
                    $stagingRows[] = $this->makeStagingRow($payload, $batchId, $sourceName);
                    $staged++;
                    continue;
                }

                $normalized = null;
                if ($hasPhone) {
                    $normalized = $this->phoneNormalizer->normalize((string) $payload['phone']);
                }

                $emirate = $this->normalizeEmirate($payload['emirate'] ?? '');
                $officialAreaId = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
                $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
                $projectId = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
                $buildingId = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');

                if ($marketingAreaId && $emirate === '') {
                    throw new \InvalidArgumentException('emirate is required for ownership');
                }

                $relationshipType = $this->normalizeRelationshipType($payload['relationship_type'] ?? null);
                $confidenceLevel = $this->normalizeConfidenceLevel($payload['confidence_level'] ?? null);

                $items[] = [
                    'row_number' => $entry['row_number'],
                    'row' => $entry['row'],
                    'payload' => $payload,
                    'source_name' => $sourceName,
                    'normalized' => $normalized,
                    'duplicate' => false,
                    'emirate' => $emirate,
                    'official_area_id' => $officialAreaId,
                    'marketing_area_id' => $marketingAreaId,
                    'project_id' => $projectId,
                    'building_id' => $buildingId,
                    'relationship_type' => $relationshipType,
                    'confidence_level' => $confidenceLevel,
                    'client_id' => null,
                    'phone_id' => null,
                ];
            } catch (Throwable $throwable) {
                $errors[] = $this->makeErrorRow($import, $entry['row_number'], $throwable->getMessage(), $entry['row']);
            }
        }

        $this->insertRows('import_staging', $stagingRows);

        if ($items !== []) {
            $this->assignClients($items);
            $this->enrichExistingClients($items);
            $duplicates = $this->upsertPhones($items);
            $this->syncPrimaryEmails($items);
            $this->syncRelationshipTags($items);
            $this->syncOwnerships($items);
            $this->insertSources($items, $import);
        }

        $this->insertRows('ivr_import_errors', $errors);

        foreach ($errors as $error) {
            Log::channel('ivr')->warning('Raw IVR import row failed.', [
                'import_id' => $import->id,
                'row_number' => $error['row_number'],
                'message' => $error['error_message'],
            ]);
        }

        return [
            'processed' => $processed,
            'successful' => count($items),
            'failed' => count($errors),
            'duplicates' => $duplicates,
            'staged' => $staged,
        ];
    }

    private function makeStagingRow(array $payload, string $batchId, string $sourceName): array
    {
        $emirate = $this->normalizeEmirate($payload['emirate'] ?? '');
        $officialAreaId = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
        $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
        $projectId = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
        $buildingId = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');
        $now = now()->toDateTimeString();

        return [
            'batch_id' => $batchId,
            'name' => $payload['name'] ?? null,
            'phone' => null,
            'email' => null,
            'country_iso' => $payload['country_iso'] ?? null,
            'emirate' => $emirate ?: null,
            'raw_official_area' => $payload['official_area_name'] ?? null,
            'raw_marketing_area' => $payload['marketing_area_name'] ?? null,
            'raw_project_name' => $payload['project_name'] ?? null,
            'raw_building_name' => $payload['building_name'] ?? null,
            'raw_unit_reference' => $payload['unit_reference'] ?? null,
            'official_area_id' => $officialAreaId,
            'marketing_area_id' => $marketingAreaId,
            'project_id' => $projectId,
            'building_id' => $buildingId,
            'relationship_type' => $payload['relationship_type'] ?? null,
            'confidence_level' => $payload['confidence_level'] ?? null,
            'source' => $sourceName,
            'status' => ImportStaging::STATUS_NEEDS_REVIEW,
            'status_reason' => 'Name only - no phone or email in source data.',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function makeErrorRow(IvrImport $import, int $rowNumber, string $message, array $row): array
    {
        $now = now()->toDateTimeString();

        return [
            'ivr_import_id' => $import->id,
            'row_number' => $rowNumber,
            'error_type' => 'row_validation',
            'error_message' => $message,
            'row_payload' => json_encode($row),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function insertRows(string $table, array $rows, int $chunkSize = self::SOURCE_INSERT_CHUNK): void
    {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function assignClients(array &$items): void
    {
        $phones = collect($items)
            ->pluck('normalized.normalized')
            ->filter()
            ->unique()
            ->values();

        $phoneRows = $phones->isEmpty()
            ? collect()
            : ClientPhoneNumber::query()
                ->whereIn('normalized_phone', $phones)
                ->get(['id', 'client_id', 'normalized_phone'])
                ->keyBy('normalized_phone');

        $clientKeys = [];
        foreach ($items as $index => $item) {
            $normalizedPhone = $item['normalized']['normalized'] ?? null;
            $phone = $normalizedPhone ? $phoneRows->get($normalizedPhone) : null;

            if ($phone?->client_id) {
                $items[$index]['client_id'] = $phone->client_id;
                $items[$index]['existing_client'] = true;
                continue;
            }

            $key = $this->clientKey($item['payload'], $item['emirate']);
            $items[$index]['client_key'] = $key;
            $clientKeys[$key] = [
                'full_name' => trim((string) ($item['payload']['name'] ?? '')) ?: null,
                'emirate' => $item['emirate'] ?: null,
                'country_iso' => $this->normalizeCountryIso($item['payload']['country_iso'] ?? null),
                'nationality' => $this->blankToNull($item['payload']['nationality'] ?? null),
                'gender' => $this->blankToNull($item['payload']['gender'] ?? null),
                'tier' => $this->normalizeTier($item['payload']['tier'] ?? null),
            ];
        }

        if ($clientKeys !== []) {
            $clientsByKey = $this->loadClientsByKeys($clientKeys);
            $missing = array_diff_key($clientKeys, $clientsByKey);

            if ($missing !== []) {
                $now = now()->toDateTimeString();
                $rows = array_map(fn (array $row): array => [
                    'full_name' => $row['full_name'],
                    'emirate' => $row['emirate'],
                    'country_iso' => $row['country_iso'],
                    'nationality' => $row['nationality'],
                    'gender' => $row['gender'],
                    'tier' => $row['tier'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], array_values($missing));

                $this->insertRows('clients', $rows);
                $clientsByKey = $this->loadClientsByKeys($clientKeys);
            }

            foreach ($items as $index => $item) {
                if ($items[$index]['client_id']) {
                    continue;
                }

                $items[$index]['client_id'] = $clientsByKey[$item['client_key']] ?? null;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $clientKeys
     * @return array<string, int>
     */
    private function loadClientsByKeys(array $clientKeys): array
    {
        $names = collect($clientKeys)->pluck('full_name')->filter()->unique()->values();

        if ($names->isEmpty()) {
            return [];
        }

        $clients = Client::query()
            ->whereIn('full_name', $names)
            ->get(['id', 'full_name', 'emirate', 'country_iso']);

        $byKey = [];
        foreach ($clients as $client) {
            $byKey[$this->clientKey([
                'name' => $client->full_name,
                'country_iso' => $client->country_iso,
            ], (string) $client->emirate)] = $client->id;
        }

        return $byKey;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function upsertPhones(array &$items): int
    {
        $byPhone = [];
        $duplicates = 0;

        foreach ($items as $index => $item) {
            $normalizedPhone = $item['normalized']['normalized'] ?? null;
            if (! $normalizedPhone) {
                continue;
            }

            if (isset($this->seenPhones[$normalizedPhone])) {
                $items[$index]['duplicate'] = true;
                $duplicates++;
            }

            if (! isset($byPhone[$normalizedPhone])) {
                $byPhone[$normalizedPhone] = [
                    'first_index' => $index,
                    'last_index' => $index,
                    'seen_before_chunk' => isset($this->seenPhones[$normalizedPhone]),
                ];
            } else {
                $byPhone[$normalizedPhone]['last_index'] = $index;
                if (! $items[$index]['duplicate']) {
                    $items[$index]['duplicate'] = true;
                    $duplicates++;
                }
            }

            $this->seenPhones[$normalizedPhone] = true;
        }

        if ($byPhone === []) {
            return $duplicates;
        }

        $phones = ClientPhoneNumber::query()
            ->whereIn('normalized_phone', array_keys($byPhone))
            ->get(['id', 'client_id', 'normalized_phone'])
            ->keyBy('normalized_phone');

        foreach ($items as $index => $item) {
            $normalizedPhone = $item['normalized']['normalized'] ?? null;
            if ($normalizedPhone && $phones->has($normalizedPhone) && ! $items[$index]['duplicate']) {
                $items[$index]['duplicate'] = true;
                $duplicates++;
            }
        }

        $now = now()->toDateTimeString();
        $upserts = [];

        foreach ($byPhone as $normalizedPhone => $meta) {
            $first = $items[$meta['first_index']];
            $last = $items[$meta['last_index']];
            $existing = $phones->get($normalizedPhone);

            if ($existing) {
                $clientId = $existing->client_id ?: $first['client_id'];
            } else {
                $clientId = $first['client_id'];
            }

            $upserts[] = [
                'client_id' => $clientId,
                'raw_phone' => $first['payload']['phone'],
                'normalized_phone' => $normalizedPhone,
                'country_code' => $first['normalized']['country_code'],
                'national_number' => $first['normalized']['national_number'],
                'detected_country' => $first['normalized']['detected_country'],
                'is_uae' => $first['normalized']['is_uae'],
                'is_primary' => true,
                'priority' => 1,
                'last_source_name' => $last['source_name'],
                'last_imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($upserts, self::SOURCE_INSERT_CHUNK) as $chunk) {
            DB::table('client_phone_numbers')->upsert(
                $chunk,
                ['normalized_phone'],
                ['client_id', 'last_source_name', 'last_imported_at', 'updated_at'],
            );
        }

        $phones = ClientPhoneNumber::query()
            ->whereIn('normalized_phone', array_keys($byPhone))
            ->get(['id', 'client_id', 'normalized_phone'])
            ->keyBy('normalized_phone');

        foreach ($items as $index => $item) {
            $normalizedPhone = $item['normalized']['normalized'] ?? null;
            if (! $normalizedPhone) {
                continue;
            }

            $phone = $phones->get($normalizedPhone);
            $items[$index]['phone_id'] = $phone?->id;
            $items[$index]['client_id'] = $phone?->client_id ?: $items[$index]['client_id'];
        }

        return $duplicates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncPrimaryEmails(array $items): void
    {
        $desired = [];

        foreach ($items as $item) {
            $email = trim((string) ($item['payload']['email'] ?? ''));
            if ($email === '' || ! $item['client_id']) {
                continue;
            }

            $desired[$item['client_id']] = [
                'client_id' => $item['client_id'],
                'email' => $email,
                'lower_email' => mb_strtolower($email),
            ];
        }

        if ($desired === []) {
            return;
        }

        $clientIds = array_keys($desired);
        $existing = ClientEmail::query()
            ->whereIn('client_id', $clientIds)
            ->get(['id', 'client_id', 'email'])
            ->mapWithKeys(fn (ClientEmail $email) => [
                $email->client_id.'|'.mb_strtolower($email->email) => $email->id,
            ]);

        DB::table('client_emails')
            ->whereIn('client_id', $clientIds)
            ->where('is_primary', true)
            ->update(['is_primary' => false, 'updated_at' => now()->toDateTimeString()]);

        $now = now()->toDateTimeString();
        $insertRows = [];
        $primaryIds = [];

        foreach ($desired as $row) {
            $key = $row['client_id'].'|'.$row['lower_email'];
            $existingId = $existing->get($key);

            if ($existingId) {
                $primaryIds[] = $existingId;
                continue;
            }

            $insertRows[] = [
                'client_id' => $row['client_id'],
                'email' => $row['email'],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insertRows, self::SOURCE_INSERT_CHUNK) as $chunk) {
            DB::table('client_emails')->insertOrIgnore($chunk);
        }

        if ($primaryIds !== []) {
            DB::table('client_emails')
                ->whereIn('id', $primaryIds)
                ->update(['is_primary' => true, 'updated_at' => $now]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncRelationshipTags(array $items): void
    {
        $tagNames = [];
        $pairs = [];

        foreach ($items as $item) {
            $relationshipType = $item['relationship_type'] ?? 'unknown';
            if ($relationshipType === 'unknown' || ! $item['client_id']) {
                continue;
            }

            $tagName = ucwords(str_replace('_', ' ', $relationshipType));
            $tagNames[$tagName] = true;
            $pairs[] = ['client_id' => $item['client_id'], 'tag_name' => $tagName];
        }

        if ($tagNames === []) {
            return;
        }

        $now = now()->toDateTimeString();
        $tagRows = array_map(fn (string $name): array => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($tagNames));

        DB::table('tags')->insertOrIgnore($tagRows);

        $tags = Tag::query()
            ->whereIn('name', array_keys($tagNames))
            ->pluck('id', 'name');

        $pivotRows = [];
        foreach ($pairs as $pair) {
            $tagId = $tags[$pair['tag_name']] ?? null;
            if (! $tagId) {
                continue;
            }

            $pivotRows[$pair['client_id'].'|'.$tagId] = [
                'client_id' => $pair['client_id'],
                'tag_id' => $tagId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($pivotRows), self::SOURCE_INSERT_CHUNK) as $chunk) {
            DB::table('client_tags')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncOwnerships(array $items): void
    {
        $rows = [];

        foreach ($items as $item) {
            if (! $item['client_id'] || ! $item['marketing_area_id']) {
                continue;
            }

            if ($item['emirate'] === '') {
                throw new \InvalidArgumentException('emirate is required for ownership');
            }

            $key = implode('|', [
                $item['client_id'],
                $item['emirate'],
                $item['marketing_area_id'],
                $item['project_id'] ?? 'null',
                $item['building_id'] ?? 'null',
                $this->blankToNull($item['payload']['unit_reference'] ?? null) ?? 'null',
                $item['relationship_type'],
            ]);

            $rows[$key] = [
                'client_id' => $item['client_id'],
                'emirate' => $item['emirate'],
                'marketing_area_id' => $item['marketing_area_id'],
                'project_id' => $item['project_id'],
                'building_id' => $item['building_id'],
                'unit_reference' => $this->blankToNull($item['payload']['unit_reference'] ?? null),
                'relationship_type' => $item['relationship_type'],
                'official_area_id' => $item['official_area_id'],
                'confidence_level' => $item['confidence_level'],
                'source' => $item['source_name'],
            ];
        }

        foreach ($rows as $row) {
            $match = [
                'client_id' => $row['client_id'],
                'emirate' => $row['emirate'],
                'marketing_area_id' => $row['marketing_area_id'],
                'project_id' => $row['project_id'],
                'building_id' => $row['building_id'],
                'unit_reference' => $row['unit_reference'],
                'relationship_type' => $row['relationship_type'],
            ];

            $values = [
                'official_area_id' => $row['official_area_id'],
                'confidence_level' => $row['confidence_level'],
                'source' => $row['source'],
                'updated_at' => now()->toDateTimeString(),
            ];

            $query = DB::table('ownerships');
            foreach ($match as $column => $value) {
                $value === null ? $query->whereNull($column) : $query->where($column, $value);
            }

            if ($query->exists()) {
                $updateQuery = DB::table('ownerships');
                foreach ($match as $column => $value) {
                    $value === null ? $updateQuery->whereNull($column) : $updateQuery->where($column, $value);
                }

                $updateQuery->update($values);

                continue;
            }

            DB::table('ownerships')->insert(array_merge(
                $match,
                $values,
                [
                    'created_at' => now()->toDateTimeString(),
                ],
            ));
        }
    }

    /**
     * For items matched to an existing client via phone number:
     * - Fills any blank/stub fields from the import row (one bulk UPDATE per field).
     * - Detects genuine conflicts (stored non-blank non-stub value differs from import after
     *   normalization) and records them in $items[$index]['field_conflicts'] so insertSources()
     *   can persist them in client_sources.metadata.
     * - Appends genuinely different names to clients.alternate_names.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function enrichExistingClients(array &$items): void
    {
        $enrichMap = [];

        foreach ($items as $item) {
            if (! ($item['existing_client'] ?? false) || ! $item['client_id']) {
                continue;
            }
            $enrichMap[$item['client_id']] ??= $item;
        }

        if ($enrichMap === []) {
            return;
        }

        $clients = Client::query()
            ->whereIn('id', array_keys($enrichMap))
            ->get(['id', 'full_name', 'alternate_names', 'emirate', 'nationality', 'gender', 'interest', 'country_iso'])
            ->keyBy('id');

        $now = now()->toDateTimeString();

        // --- Per-item conflict detection (loops all items, not just the enrichMap first-per-client) ---
        $newAlternateNames = []; // [client_id => [normalised_name, ...]]

        foreach ($items as $index => $item) {
            if (! ($item['existing_client'] ?? false) || ! $item['client_id']) {
                continue;
            }

            $client = $clients->get($item['client_id']);
            if (! $client) {
                continue;
            }

            $importedName = NameNormalizer::normalize(trim((string) ($item['payload']['name'] ?? '')));
            $storedName   = NameNormalizer::normalize((string) ($client->full_name ?? ''));

            $fieldConflicts = [];

            // Name conflict: stored is real (non-blank, non-stub) but differs after normalization
            if (
                $importedName !== ''
                && $storedName !== ''
                && $storedName !== $importedName
                && ! RawContactImportEnricher::isStubName((string) $client->full_name)
            ) {
                $fieldConflicts['full_name'] = [
                    'stored'   => $client->full_name,
                    'imported' => $importedName,
                ];
                $newAlternateNames[$item['client_id']][] = $importedName;
            }

            // Other field conflicts: both sides non-blank and different
            foreach ([
                'emirate'     => $item['emirate'] ?: null,
                'nationality' => $this->blankToNull($item['payload']['nationality'] ?? null),
                'gender'      => $this->blankToNull($item['payload']['gender'] ?? null),
                'interest'    => $this->blankToNull($item['payload']['interest'] ?? null),
                'country_iso' => strtoupper(substr(trim((string) ($item['payload']['country_iso'] ?? '')), 0, 2)) ?: null,
            ] as $field => $importedValue) {
                $storedValue = $client->$field;
                if (
                    $importedValue !== null
                    && $storedValue !== null && $storedValue !== ''
                    && $importedValue !== $storedValue
                ) {
                    $fieldConflicts[$field] = [
                        'stored'   => $storedValue,
                        'imported' => $importedValue,
                    ];
                }
            }

            if ($fieldConflicts !== []) {
                $items[$index]['field_conflicts'] = $fieldConflicts;
            }
        }

        // --- Merge new alternate names onto clients ---
        foreach ($newAlternateNames as $clientId => $names) {
            $existing = $clients->get($clientId)?->alternate_names ?? [];
            $merged   = array_values(array_unique(array_merge($existing, $names)));

            DB::table('clients')->where('id', $clientId)->update([
                'alternate_names' => json_encode($merged),
                'updated_at'      => $now,
            ]);
        }

        // --- Fill blank and stub fields (one bulk UPDATE per field) ---
        $fieldResolvers = [
            'full_name'   => fn ($item) => NameNormalizer::normalize(trim((string) ($item['payload']['name'] ?? ''))),
            'emirate'     => fn ($item) => $item['emirate'] ?: null,
            'nationality' => fn ($item) => $this->blankToNull($item['payload']['nationality'] ?? null),
            'gender'      => fn ($item) => $this->blankToNull($item['payload']['gender'] ?? null),
            'interest'    => fn ($item) => $this->blankToNull($item['payload']['interest'] ?? null),
            'country_iso' => fn ($item) => strtoupper(substr(trim((string) ($item['payload']['country_iso'] ?? '')), 0, 2)) ?: null,
        ];

        foreach ($fieldResolvers as $field => $getValue) {
            $blankFills = []; // client_id => value  (stored null/empty)
            $stubFills  = []; // client_id => value  (stored stub name, full_name only)

            foreach ($clients as $clientId => $client) {
                $stored  = $client->$field;
                $isBlank = $stored === null || $stored === '';
                $isStub  = $field === 'full_name' && ! $isBlank
                    && RawContactImportEnricher::isStubName((string) $stored);

                if (! $isBlank && ! $isStub) {
                    continue;
                }

                $value = $getValue($enrichMap[$clientId]);
                if ($value === null || $value === '') {
                    continue;
                }

                if ($isBlank) {
                    $blankFills[$clientId] = $value;
                } else {
                    $stubFills[$clientId] = $value;
                }
            }

            // Bulk UPDATE for blank fields (guarded by IS NULL OR = '' in SQL)
            if ($blankFills !== []) {
                $cases    = implode(' ', array_map(fn () => 'WHEN ? THEN ?', $blankFills));
                $bindings = [];
                foreach ($blankFills as $id => $value) {
                    $bindings[] = $id;
                    $bindings[] = $value;
                }
                $ids = implode(',', array_fill(0, count($blankFills), '?'));

                DB::statement(
                    "UPDATE clients SET {$field} = CASE id {$cases} END, updated_at = ? WHERE id IN ({$ids}) AND ({$field} IS NULL OR {$field} = '')",
                    [...$bindings, $now, ...array_keys($blankFills)],
                );
            }

            // Bulk UPDATE for stub name replacement (full_name only, no null guard needed)
            if ($stubFills !== []) {
                $cases    = implode(' ', array_map(fn () => 'WHEN ? THEN ?', $stubFills));
                $bindings = [];
                foreach ($stubFills as $id => $value) {
                    $bindings[] = $id;
                    $bindings[] = $value;
                }
                $ids = implode(',', array_fill(0, count($stubFills), '?'));

                DB::statement(
                    "UPDATE clients SET full_name = CASE id {$cases} END, updated_at = ? WHERE id IN ({$ids})",
                    [...$bindings, $now, ...array_keys($stubFills)],
                );
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function insertSources(array $items, IvrImport $import): void
    {
        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                'client_id' => $item['client_id'],
                'client_phone_number_id' => $item['phone_id'],
                'channel' => 'ivr',
                'source_type' => 'raw_import',
                'source_name' => $item['source_name'],
                'source_file_name' => $import->original_file_name,
                'source_reference' => (string) $import->id,
                'metadata' => json_encode([
                    'duplicate'             => (bool) $item['duplicate'],
                    'raw_name'              => $this->blankToNull($item['payload']['name'] ?? null),
                    'raw_emirate'           => $this->blankToNull($item['payload']['emirate'] ?? null),
                    'raw_nationality'       => $this->blankToNull($item['payload']['nationality'] ?? null),
                    'raw_gender'            => $this->blankToNull($item['payload']['gender'] ?? null),
                    'raw_interest'          => $this->blankToNull($item['payload']['interest'] ?? null),
                    'raw_official_area'     => $this->blankToNull($item['payload']['official_area_name'] ?? null),
                    'raw_marketing_area'    => $this->blankToNull($item['payload']['marketing_area_name'] ?? null),
                    'raw_project'           => $this->blankToNull($item['payload']['project_name'] ?? null),
                    'raw_building'          => $this->blankToNull($item['payload']['building_name'] ?? null),
                    'raw_unit'              => $this->blankToNull($item['payload']['unit_reference'] ?? null),
                    'raw_relationship_type' => $this->blankToNull($item['payload']['relationship_type'] ?? null),
                    'field_conflicts'       => $item['field_conflicts'] ?? null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertRows('client_sources', $rows);
    }

    private function clientKey(array $payload, string $emirate): string
    {
        return implode('|', [
            trim((string) ($payload['name'] ?? '')),
            $emirate ?: '',
            $this->normalizeCountryIso($payload['country_iso'] ?? null) ?: '',
        ]);
    }

    private function normalizeCountryIso(?string $value): ?string
    {
        return strtoupper(substr(trim((string) $value), 0, 2)) ?: null;
    }

    private function normalizeRelationshipType(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        if ($normalized === '') {
            return 'unknown';
        }

        if (! in_array($normalized, self::RELATIONSHIP_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid relationship type: {$value}");
        }

        return $normalized;
    }

    private function normalizeConfidenceLevel(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (! in_array($normalized, self::CONFIDENCE_LEVELS, true)) {
            throw new \InvalidArgumentException("Invalid confidence level: {$value}");
        }

        return $normalized;
    }

    private function normalizeTier(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        $v = str_replace([' ', '-'], '_', $v);

        return in_array($v, ['standard', 'premium', 'high_net_worth', 'vip'], true) ? $v : null;
    }

    private function blankToNull(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }

    /**
     * Normalise a raw value from the CSV "emirate" (or aliased "city"/"state")
     * column to one of the seven canonical UAE emirate names.
     *
     * Returns an empty string when the value is unrecognisable so that callers
     * treat it consistently with a missing column (stored as NULL in the DB).
     */
    private function normalizeEmirate(?string $value): string
    {
        $v = trim((string) $value);

        if ($v === '') {
            return '';
        }

        return self::EMIRATE_ALIASES[mb_strtolower($v)] ?? '';
    }

    private function flushSourceBuffer(): void
    {
        if ($this->sourceBuffer === []) {
            return;
        }

        DB::table('client_sources')->insert($this->sourceBuffer);
        $this->sourceBuffer = [];
    }

    private function applyRelationshipTag(Client $client, ?string $relationshipType): void
    {
        $normalized = strtolower(trim((string) $relationshipType));

        if ($normalized === '' || $normalized === 'unknown') {
            return;
        }

        // "buyer_interest" → "Buyer Interest", "owner" → "Owner"
        $tagName = ucwords(str_replace('_', ' ', $normalized));

        if (! isset($this->tagCache[$tagName])) {
            $this->tagCache[$tagName] = Tag::firstOrCreate(['name' => $tagName]);
        }

        $client->tags()->syncWithoutDetaching([$this->tagCache[$tagName]->id]);
    }

    private function upsertClientFromPayload(array $payload, string $sourceName, IvrImport $import): bool
    {
        $phone = ($payload['phone'] ?? '') !== '' ? $payload['phone'] : null;
        $normalized = $phone ? $this->phoneNormalizer->normalize($phone) : null;

        $phoneNumber = $normalized
            ? ClientPhoneNumber::query()->where('normalized_phone', $normalized['normalized'])->first()
            : null;

        $duplicate = $phoneNumber !== null;

        $emirate = $this->normalizeEmirate($payload['emirate'] ?? '');

        $officialAreaId  = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
        $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
        $projectId       = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
        $buildingId      = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');

        $enrichPayload = array_merge($payload, [
            'normalized_phone' => $normalized['normalized'] ?? null,
            'emirate'          => $emirate,
        ]);

        $client = $this->enricher->resolveClient($enrichPayload, $phoneNumber);

        $this->applyRelationshipTag($client, $payload['relationship_type'] ?? null);

        if ($normalized) {
            if (! $phoneNumber) {
                $phoneNumber = ClientPhoneNumber::create([
                    'client_id'        => $client->id,
                    'raw_phone'        => $phone,
                    'normalized_phone' => $normalized['normalized'],
                    'country_code'     => $normalized['country_code'],
                    'national_number'  => $normalized['national_number'],
                    'detected_country' => $normalized['detected_country'],
                    'is_uae'           => $normalized['is_uae'],
                    'is_primary'       => true,
                    'priority'         => 1,
                    'last_source_name' => $sourceName,
                    'last_imported_at' => now(),
                ]);
            } else {
                $phoneNumber->forceFill([
                    'client_id'        => $client->id,
                    'last_source_name' => $sourceName,
                    'last_imported_at' => now(),
                ])->save();
            }
        }

        if ($marketingAreaId) {
            $this->enricher->syncOwnership(
                client: $client,
                payload: $enrichPayload,
                officialAreaId: $officialAreaId,
                marketingAreaId: $marketingAreaId,
                projectId: $projectId,
                buildingId: $buildingId,
                sourceName: $sourceName,
            );
        }

        $now = now()->toDateTimeString();
        $this->sourceBuffer[] = [
            'client_id'              => $client->id,
            'client_phone_number_id' => $phoneNumber?->id,
            'channel'                => 'ivr',
            'source_type'            => 'raw_import',
            'source_name'            => $sourceName,
            'source_file_name'       => $import->original_file_name,
            'source_reference'       => (string) $import->id,
            'metadata'               => json_encode(['duplicate' => $duplicate]),
            'created_at'             => $now,
            'updated_at'             => $now,
        ];

        return $duplicate;
    }

}
