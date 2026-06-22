<?php

namespace App\Modules\IVR\Support;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Enums\IvrImportStatus;
use App\Modules\IVR\Models\IvrImport;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

class UnsubscriberImportProcessor
{
    use CsvRowTrait;

    private const ACTIVE_UNSUBSCRIBE_REASONS = ['unsubscribe', 'customer_unsubscribed'];

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly NumberEligibilityService $eligibilityService,
    ) {
    }

    public function process(IvrImport $import): void
    {
        $import->update([
            'status' => IvrImportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $file = new SplFileObject(storage_path('app/private/'.$import->storage_path));
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

            $totalRows = $this->countDataRows($file);

            $import->update([
                'total_rows' => $totalRows,
                'summary' => [
                    'format' => 'phone,name,reason',
                    'created_rows' => 0,
                    'existing_rows' => 0,
                ],
            ]);

            $file->rewind();

            $processed = 0;
            $created = 0;
            $existing = 0;
            $failed = 0;
            $rowNumber = 0;

            while (! $file->eof()) {
                $row = $file->fgetcsv();
                $rowNumber++;

                if (! is_array($row) || $this->rowIsEmpty($row)) {
                    continue;
                }

                if ($rowNumber === 1 && $this->looksLikeHeader($row)) {
                    continue;
                }

                $processed++;

                try {
                    $wasCreated = $this->upsertUnsubscriber(
                        phone: trim((string) ($row[0] ?? '')),
                        name: trim((string) ($row[1] ?? '')) ?: null,
                        reason: trim((string) ($row[2] ?? '')) ?: null,
                        sourceFile: $import->original_file_name,
                        rowNumber: $rowNumber,
                    );

                    $wasCreated ? $created++ : $existing++;
                } catch (Throwable $throwable) {
                    $failed++;

                    $import->errors()->create([
                        'row_number' => $rowNumber,
                        'error_type' => 'row_validation',
                        'error_message' => $throwable->getMessage(),
                        'row_payload' => $row,
                    ]);
                }

                if ($processed % 250 === 0) {
                    $this->updateProgress($import, $processed, $created, $existing, $failed);
                }
            }

            $import->update([
                'status' => $failed > 0 ? IvrImportStatus::CompletedWithErrors : IvrImportStatus::Completed,
                'total_rows' => $processed,
                'processed_rows' => $processed,
                'successful_rows' => $created,
                'failed_rows' => $failed,
                'duplicate_rows' => $existing,
                'completed_at' => now(),
                'summary' => [
                    'format' => 'phone,name,reason',
                    'created_rows' => $created,
                    'existing_rows' => $existing,
                ],
            ]);

            $this->notifyUploader($import, $created, $existing, $failed);
        } catch (Throwable $throwable) {
            $import->update([
                'status' => IvrImportStatus::Failed,
                'error_message' => $throwable->getMessage(),
                'completed_at' => now(),
            ]);

            Log::channel('ivr')->error('Unsubscriber import failed.', [
                'import_id' => $import->id,
                'message' => $throwable->getMessage(),
            ]);

            if ($recipient = $import->user) {
                Notification::make()
                    ->title('Do Not Call import failed — '.$import->original_file_name)
                    ->body($throwable->getMessage())
                    ->danger()
                    ->sendToDatabase($recipient);
            }
        }
    }

    /** Ping the person who uploaded the file in the admin bell when it finishes. */
    private function notifyUploader(IvrImport $import, int $created, int $existing, int $failed): void
    {
        $recipient = $import->user;

        if (! $recipient) {
            return;
        }

        $body = number_format($created).' added to Do Not Call, '.number_format($existing).' already listed'
            .($failed > 0 ? ', '.number_format($failed).' failed' : '').'.';

        $notification = Notification::make()
            ->title('Do Not Call import finished — '.$import->original_file_name)
            ->body($body);

        ($failed > 0 ? $notification->warning() : $notification->success())
            ->sendToDatabase($recipient);
    }

    private function updateProgress(IvrImport $import, int $processed, int $created, int $existing, int $failed): void
    {
        $import->update([
            'processed_rows' => $processed,
            'successful_rows' => $created,
            'failed_rows' => $failed,
            'duplicate_rows' => $existing,
            'summary' => array_merge($import->summary ?? [], [
                'created_rows' => $created,
                'existing_rows' => $existing,
            ]),
        ]);
    }

    private function countDataRows(SplFileObject $file): int
    {
        $count = 0;
        $rowNumber = 0;

        while (! $file->eof()) {
            $row = $file->fgetcsv();
            $rowNumber++;

            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }

            if ($rowNumber === 1 && $this->looksLikeHeader($row)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function upsertUnsubscriber(string $phone, ?string $name, ?string $reason, string $sourceFile, int $rowNumber): bool
    {
        if ($phone === '') {
            throw new \RuntimeException('Phone number is required.');
        }

        $normalized = $this->phoneNormalizer->normalize($phone);

        return DB::transaction(function () use ($phone, $name, $reason, $sourceFile, $rowNumber, $normalized): bool {
            $phoneNumber = ClientPhoneNumber::query()
                ->where('normalized_phone', $normalized['normalized'])
                ->first();

            if (! $phoneNumber) {
                $client = Client::create([
                    'full_name' => $name,
                ]);

                $phoneNumber = ClientPhoneNumber::create([
                    'client_id' => $client->id,
                    'raw_phone' => $phone,
                    'normalized_phone' => $normalized['normalized'],
                    'country_code' => $normalized['country_code'],
                    'national_number' => $normalized['national_number'],
                    'detected_country' => $normalized['detected_country'],
                    'is_uae' => $normalized['is_uae'],
                    'is_primary' => true,
                    'priority' => 1,
                    'unsubscribed_at' => now(),
                ]);
            } else {
                $client = $phoneNumber->client ?: Client::create(['full_name' => $name]);

                if ($name && trim((string) $client->full_name) === '') {
                    $client->forceFill(['full_name' => $name])->save();
                }

                $phoneNumber->forceFill([
                    'client_id' => $client->id,
                    'raw_phone' => $phone,
                    'unsubscribed_at' => $phoneNumber->unsubscribed_at ?: now(),
                ])->save();
            }

            $existing = ContactSuppression::query()
                ->where('client_phone_number_id', $phoneNumber->id)
                ->where('channel', 'ivr')
                ->whereIn('reason', self::ACTIVE_UNSUBSCRIBE_REASONS)
                ->whereNull('released_at')
                ->first();

            if ($existing) {
                // Backfill the opt-out reason onto an existing DNC entry that doesn't have one yet
                // (re-import to add reasons), but never clobber a reason already on record.
                if ($reason !== null && ($existing->context['reason'] ?? null) === null) {
                    $existing->forceFill([
                        'context' => array_merge($existing->context ?? [], ['reason' => $reason]),
                    ])->save();
                }

                $this->eligibilityService->refresh($phoneNumber->refresh());

                return false;
            }

            $context = [
                'source' => 'unsubscriber_import',
                'source_file' => $sourceFile,
                'row_number' => $rowNumber,
                'name' => $name,
            ];

            if ($reason !== null) {
                $context['reason'] = $reason;
            }

            ContactSuppression::create([
                'client_phone_number_id' => $phoneNumber->id,
                'channel' => 'ivr',
                'reason' => 'unsubscribe',
                'context' => $context,
                'suppressed_at' => now(),
            ]);

            $this->eligibilityService->refresh($phoneNumber->refresh());

            return true;
        });
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        return str_contains(strtolower((string) ($row[0] ?? '')), 'phone')
            || str_contains(strtolower((string) ($row[1] ?? '')), 'name');
    }
}
