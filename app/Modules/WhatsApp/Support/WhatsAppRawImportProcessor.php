<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ClientSource;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use App\Support\LocationResolver;
use App\Support\RawContactImportEnricher;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use SplFileObject;
use Throwable;

class WhatsAppRawImportProcessor
{
    private LocationResolver $resolver;
    private RawContactImportEnricher $enricher;

    public function __construct(
        private readonly WhatsAppRawImportColumnMapper $mapper,
        private readonly WhatsAppPhoneNormalizer $phoneNormalizer,
    ) {
        $this->resolver = new LocationResolver();
        $this->enricher = new RawContactImportEnricher();
    }

    public function process(WhatsAppImport $import): void
    {
        ini_set('memory_limit', '512M');

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $import->update([
            'status'    => WhatsAppImportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);
        $import->broadcastProgress();

        Log::channel('whatsapp')->info('Starting WhatsApp raw import.', ['import_id' => $import->id]);

        try {
            $file = new SplFileObject(storage_path('app/private/' . $import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $header = $this->readHeader($file);

            if ($import->column_mapping !== null) {
                $mapping = ['mapped' => $import->column_mapping, 'missing' => []];
            } else {
                $mapping = $this->mapper->map($header);

                if ($mapping['missing'] !== []) {
                    throw new \RuntimeException('Missing required columns: ' . implode(', ', $mapping['missing']));
                }
            }

            $import->update(['total_rows' => $this->countDataRows($file)]);
            $import->broadcastProgress();
            $file->rewind();
            $this->readHeader($file);

            $processed  = 0;
            $successful = 0;
            $failed     = 0;
            $duplicates = 0;
            $rowNumber  = 1;
            $sourceFallback = $import->source_name ?: pathinfo($import->original_file_name, PATHINFO_FILENAME);

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $processed++;

                try {
                    $payload   = $this->extractPayload($row, $mapping['mapped']);
                    $sourceName = ($payload['source_file'] ?? null) ?: $sourceFallback;
                    $duplicate = $this->upsertClient($payload, $sourceName, $import);

                    $successful++;
                    $duplicates += $duplicate ? 1 : 0;
                } catch (Throwable $e) {
                    $failed++;

                    $import->errors()->create([
                        'row_number'    => $rowNumber,
                        'error_type'    => 'row_validation',
                        'error_message' => $e->getMessage(),
                        'row_payload'   => $row ?? null,
                    ]);

                    Log::channel('whatsapp')->warning('WhatsApp raw import row failed.', [
                        'import_id'  => $import->id,
                        'row_number' => $rowNumber,
                        'message'    => $e->getMessage(),
                    ]);
                }

                if ($processed % 250 === 0) {
                    $import->update([
                        'processed_rows'  => $processed,
                        'successful_rows' => $successful,
                        'failed_rows'     => $failed,
                        'duplicate_rows'  => $duplicates,
                    ]);
                    $import->broadcastProgress();
                }
            }

            $import->update([
                'status'          => $failed > 0 ? WhatsAppImportStatus::CompletedWithErrors : WhatsAppImportStatus::Completed,
                'total_rows'      => $processed,
                'processed_rows'  => $processed,
                'successful_rows' => $successful,
                'failed_rows'     => $failed,
                'duplicate_rows'  => $duplicates,
                'completed_at'    => now(),
                'summary'         => [
                    'required_columns' => config('whatsapp.raw_import.required'),
                    'mapped_columns'   => array_keys($mapping['mapped']),
                ],
            ]);
            $import->broadcastProgress();

            Log::channel('whatsapp')->info('Completed WhatsApp raw import.', ['import_id' => $import->id]);
        } catch (Throwable $e) {
            $import->update([
                'status'       => WhatsAppImportStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $import->broadcastProgress();

            Log::channel('whatsapp')->error('WhatsApp raw import failed.', [
                'import_id' => $import->id,
                'message'   => $e->getMessage(),
            ]);
        } finally {
            if (class_exists(Telescope::class)) {
                Telescope::startRecording();
            }
        }
    }

    /** @return array<int, string> */
    private function readHeader(SplFileObject $file): array
    {
        while (! $file->eof()) {
            $header = $file->fgetcsv();
            if (is_array($header) && ! $this->rowIsEmpty($header)) {
                $header = array_map(fn ($v) => (string) $v, $header);
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

    /** @param array<int, mixed> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>       $mapping
     * @return array<string, string|null>
     */
    private function extractPayload(array $row, array $mapping): array
    {
        $payload = [];
        foreach ($mapping as $column => $index) {
            $payload[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        if (($payload['name'] ?? '') === '') {
            throw new \RuntimeException('Name is required.');
        }
        if (($payload['phone'] ?? '') === '') {
            throw new \RuntimeException('Phone is required.');
        }

        return $payload;
    }

    private function upsertClient(array $payload, string $sourceName, WhatsAppImport $import): bool
    {
        $normalized  = $this->phoneNormalizer->normalize((string) $payload['phone']);
        $phoneNumber = ClientPhoneNumber::query()->where('normalized_phone', $normalized['normalized'])->first();
        $duplicate   = $phoneNumber !== null;

        $emirate = trim((string) ($payload['emirate'] ?? ''));

        $officialAreaId  = $this->resolver->officialAreaId($emirate, $payload['official_area_name'] ?? '');
        $marketingAreaId = $this->resolver->marketingAreaId($emirate, $payload['marketing_area_name'] ?? '');
        $projectId       = $this->resolver->projectId($marketingAreaId, $payload['project_name'] ?? '');
        $buildingId      = $this->resolver->buildingId($projectId, $payload['building_name'] ?? '');

        $enrichPayload = array_merge($payload, [
            'normalized_phone' => $normalized['normalized'],
            'emirate'          => $emirate,
        ]);

        $client = $this->enricher->resolveClient($enrichPayload, $phoneNumber);

        if (! $phoneNumber) {
            $phoneNumber = ClientPhoneNumber::create([
                'client_id'        => $client->id,
                'raw_phone'        => $payload['phone'],
                'normalized_phone' => $normalized['normalized'],
                'country_code'     => $normalized['country_code'],
                'national_number'  => $normalized['national_number'],
                'detected_country' => $normalized['detected_country'],
                'is_uae'           => $normalized['is_uae'],
                'is_whatsapp'      => true,
                'is_primary'       => true,
                'priority'         => 1,
                'last_source_name' => $sourceName,
                'last_imported_at' => now(),
            ]);
        } else {
            $phoneNumber->forceFill([
                'client_id'        => $client->id,
                'is_whatsapp'      => true,
                'last_source_name' => $sourceName,
                'last_imported_at' => now(),
            ])->save();
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

        $blankToNull = fn (?string $v): ?string => ($t = trim((string) $v)) !== '' ? $t : null;

        ClientSource::create([
            'client_id'              => $client->id,
            'client_phone_number_id' => $phoneNumber->id,
            'channel'                => 'whatsapp',
            'source_type'            => 'raw_import',
            'source_name'            => $sourceName,
            'source_file_name'       => $import->original_file_name,
            'source_reference'       => (string) $import->id,
            'metadata'               => [
                'duplicate'             => $duplicate,
                'raw_name'              => $blankToNull($payload['name'] ?? null),
                'raw_emirate'           => $blankToNull($payload['emirate'] ?? null),
                'raw_nationality'       => $blankToNull($payload['nationality'] ?? null),
                'raw_gender'            => $blankToNull($payload['gender'] ?? null),
                'raw_interest'          => $blankToNull($payload['interest'] ?? null),
                'raw_official_area'     => $blankToNull($payload['official_area_name'] ?? null),
                'raw_marketing_area'    => $blankToNull($payload['marketing_area_name'] ?? null),
                'raw_project'           => $blankToNull($payload['project_name'] ?? null),
                'raw_building'          => $blankToNull($payload['building_name'] ?? null),
                'raw_unit'              => $blankToNull($payload['unit_reference'] ?? null),
                'raw_relationship_type' => $blankToNull($payload['relationship_type'] ?? null),
            ],
        ]);

        return $duplicate;
    }
}
