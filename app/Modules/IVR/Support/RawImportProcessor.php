<?php

namespace App\Modules\IVR\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Models\IvrImport;
use App\Support\LocationResolver;
use App\Support\RawContactImportEnricher;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

class RawImportProcessor
{
    use CsvRowTrait;

    private LocationResolver $resolver;
    private RawContactImportEnricher $enricher;

    public function __construct(
        private readonly RawImportColumnMapper $mapper,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {
        $this->resolver = new LocationResolver();
        $this->enricher = new RawContactImportEnricher();
    }

    public function process(IvrImport $import): void
    {
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
            $rowNumber = 1;
            $sourceFallback = $import->source_name ?: pathinfo($import->original_file_name, PATHINFO_FILENAME);

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $processed++;

                try {
                    $payload = $this->extractPayload($row, $mapping['mapped']);
                    $sourceName = ($payload['source_file'] ?? null) ?: $sourceFallback;
                    $duplicate = $this->upsertClientFromPayload($payload, $sourceName, $import);

                    $successful++;
                    $duplicates += $duplicate ? 1 : 0;
                } catch (Throwable $throwable) {
                    $failed++;

                    $import->errors()->create([
                        'row_number' => $rowNumber,
                        'error_type' => 'row_validation',
                        'error_message' => $throwable->getMessage(),
                        'row_payload' => $row,
                    ]);

                    Log::channel('ivr')->warning('Raw IVR import row failed.', [
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
                    $import->broadcastProgress();
                }
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
                ],
            ]);
            $import->broadcastProgress();

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
        }
    }

    /**
     * @return array<int, string>
     */
    private function readHeader(SplFileObject $file): array
    {
        while (! $file->eof()) {
            $header = $file->fgetcsv();

            if (is_array($header) && ! $this->rowIsEmpty($header)) {
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

            if (is_array($row) && ! $this->rowIsEmpty($row)) {
                $count++;
            }
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

        if (($payload['phone'] ?? null) === null || ($payload['phone'] ?? '') === '') {
            throw new \RuntimeException('Phone is required.');
        }

        return $payload;
    }

    private function upsertClientFromPayload(array $payload, string $sourceName, IvrImport $import): bool
    {
        $normalized = $this->phoneNormalizer->normalize((string) $payload['phone']);

        $phoneNumber = ClientPhoneNumber::query()->where('normalized_phone', $normalized['normalized'])->first();
        $duplicate   = $phoneNumber !== null;

        $emirate = trim((string) ($payload['emirate'] ?? ''));

        // Resolve new geography FKs
        $officialAreaId  = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
        $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
        $projectId       = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
        $buildingId      = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');

        $enrichPayload = array_merge($payload, [
            'normalized_phone' => $normalized['normalized'],
            'emirate'          => $emirate,
        ]);

        $client = $this->enricher->resolveClient($enrichPayload);

        if (! $phoneNumber) {
            $phoneNumber = ClientPhoneNumber::create([
                'client_id'        => $client->id,
                'raw_phone'        => $payload['phone'],
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

        // Sync ownership if we have at least a marketing area
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

        ClientSource::create([
            'client_id'              => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel'                => 'ivr',
            'source_type'            => 'raw_import',
            'source_name'            => $sourceName,
            'source_file_name'       => $import->original_file_name,
            'source_reference'       => (string) $import->id,
            'metadata'               => ['duplicate' => $duplicate],
        ]);

        return $duplicate;
    }

}
