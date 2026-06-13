<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use SplFileObject;
use Throwable;

class WhatsAppUnsubscriberImportProcessor
{
    public function __construct(
        private readonly WhatsAppPhoneNormalizer $phoneNormalizer,
    ) {}

    public function process(WhatsAppImport $import): void
    {
        ini_set('memory_limit', '512M');

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $import->update([
            'status'        => WhatsAppImportStatus::Processing->value,
            'started_at'    => now(),
            'error_message' => null,
        ]);

        Log::channel('whatsapp')->info('Starting WhatsApp unsubscriber import.', ['import_id' => $import->id]);

        try {
            $file = new SplFileObject(storage_path('app/private/'.$import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $totalRows = $this->countDataRows($file);
            $import->update(['total_rows' => $totalRows]);

            $file->rewind();
            $file->fgetcsv(); // skip header

            $platform = $import->platform();

            $processed  = 0;
            $successful = 0;
            $failed     = 0;
            $duplicates = 0;
            $rowNumber  = 1;

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                $processed++;

                try {
                    $rawPhone = trim((string) ($row[0] ?? ''));
                    $name     = trim((string) ($row[1] ?? ''));

                    if ($rawPhone === '') {
                        throw new \RuntimeException('Phone number is required.');
                    }

                    $normalized     = $this->phoneNormalizer->normalize($rawPhone);
                    $normalizedPhone = $normalized['normalized'];

                    $phoneNumber = ClientPhoneNumber::query()
                        ->where('normalized_phone', $normalizedPhone)
                        ->first();

                    if (! $phoneNumber) {
                        $client = Client::create(['full_name' => $name ?: null]);

                        $phoneNumber = ClientPhoneNumber::create([
                            'client_id'        => $client->id,
                            'raw_phone'        => $rawPhone,
                            'normalized_phone' => $normalizedPhone,
                            'country_code'     => $normalized['country_code'],
                            'national_number'  => $normalized['national_number'],
                            'detected_country' => $normalized['detected_country'],
                            'is_uae'           => $normalized['is_uae'],
                            'is_whatsapp'      => false,
                        ]);
                    } elseif ($name !== '' && ! $phoneNumber->client?->full_name) {
                        $phoneNumber->client?->update(['full_name' => $name]);
                    }

                    $alreadySuppressed = ContactSuppression::query()
                        ->where('client_phone_number_id', $phoneNumber->id)
                        ->activeWhatsApp($platform?->value)
                        ->exists();

                    if ($alreadySuppressed) {
                        $duplicates++;
                        $successful++;
                    } else {
                        ContactSuppression::create([
                            'client_phone_number_id' => $phoneNumber->id,
                            'channel'                => 'whatsapp',
                            'platform'               => $platform?->value,
                            'reason'                 => 'opted_out',
                            'suppressed_at'          => now(),
                            'context'                => [
                                'source'      => 'import',
                                'source_file' => $import->original_file_name,
                            ],
                        ]);

                        $successful++;
                    }
                } catch (Throwable $e) {
                    $failed++;

                    $import->errors()->create([
                        'row_number'    => $rowNumber,
                        'error_type'    => 'row_validation',
                        'error_message' => $e->getMessage(),
                        'row_payload'   => $row ?? null,
                    ]);

                    Log::channel('whatsapp')->warning('Unsubscriber import row failed.', [
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
                }
            }

            $import->update([
                'status'          => $failed > 0
                    ? WhatsAppImportStatus::CompletedWithErrors->value
                    : WhatsAppImportStatus::Completed->value,
                'total_rows'      => $processed,
                'processed_rows'  => $processed,
                'successful_rows' => $successful,
                'failed_rows'     => $failed,
                'duplicate_rows'  => $duplicates,
                'completed_at'    => now(),
            ]);

            Log::channel('whatsapp')->info('Completed WhatsApp unsubscriber import.', ['import_id' => $import->id]);
        } catch (Throwable $e) {
            $import->update([
                'status'        => WhatsAppImportStatus::Failed->value,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            Log::channel('whatsapp')->error('Unsubscriber import failed.', [
                'import_id' => $import->id,
                'message'   => $e->getMessage(),
            ]);
        } finally {
            if (class_exists(Telescope::class)) {
                Telescope::startRecording();
            }
        }
    }

    private function countDataRows(SplFileObject $file): int
    {
        $count      = 0;
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
