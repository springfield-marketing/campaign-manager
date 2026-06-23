<?php

namespace App\Modules\WhatsApp\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\WhatsApp\Enums\WhatsAppImportStatus;
use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use App\Modules\WhatsApp\Models\WhatsAppImport;
use Filament\Notifications\Notification;
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
                    $reason   = trim((string) ($row[2] ?? '')) ?: null;

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

                    $existingSuppression = ContactSuppression::query()
                        ->where('client_phone_number_id', $phoneNumber->id)
                        ->activeWhatsApp($platform?->value)
                        ->first();

                    if ($existingSuppression) {
                        // Backfill the opt-out reason onto an existing DNC entry that doesn't have
                        // one yet (re-import to add reasons); never clobber a reason already on record.
                        if ($reason !== null && ($existingSuppression->context['reason'] ?? null) === null) {
                            $existingSuppression->forceFill([
                                'context' => array_merge($existingSuppression->context ?? [], ['reason' => $reason]),
                            ])->save();
                        }

                        $duplicates++;
                        $successful++;
                    } else {
                        $context = [
                            'source'      => 'import',
                            'source_file' => $import->original_file_name,
                        ];

                        if ($reason !== null) {
                            $context['reason'] = $reason;
                        }

                        ContactSuppression::create([
                            'client_phone_number_id' => $phoneNumber->id,
                            'channel'                => 'whatsapp',
                            'platform'               => $platform?->value,
                            'reason'                 => 'opted_out',
                            'suppressed_at'          => now(),
                            'context'                => $context,
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

            $this->notifyUploader($import, $successful - $duplicates, $duplicates, $failed);
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

            // Surface the whole-import failure in Sentry (no-op if no DSN). Per-row errors above
            // are intentionally not sent — a bad file could hold thousands and would flood Sentry.
            \Sentry\captureException($e);

            if ($recipient = $import->user) {
                Notification::make()
                    ->title('Unsubscriber import failed — '.$import->original_file_name)
                    ->body($e->getMessage())
                    ->danger()
                    ->sendToDatabase($recipient);
            }
        } finally {
            if (class_exists(Telescope::class)) {
                Telescope::startRecording();
            }
        }
    }

    /** Ping the person who uploaded the file in the admin bell when it finishes. */
    private function notifyUploader(WhatsAppImport $import, int $added, int $existing, int $failed): void
    {
        $recipient = $import->user;

        if (! $recipient) {
            return;
        }

        $body = number_format($added).' added to Do Not Call, '.number_format($existing).' already listed'
            .($failed > 0 ? ', '.number_format($failed).' failed' : '').'.';

        $notification = Notification::make()
            ->title('Unsubscriber import finished — '.$import->original_file_name)
            ->body($body);

        ($failed > 0 ? $notification->warning() : $notification->success())
            ->sendToDatabase($recipient);
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
