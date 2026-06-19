<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Models\IvrImport;
use App\Modules\IVR\Support\NumberEligibilityService;
use App\Modules\IVR\Support\PhoneNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;
use SplFileObject;
use Throwable;

class RestoreIvrUnsubscriberSuppressions extends Command
{
    protected $signature = 'ivr:restore-unsubscriber-suppressions
                            {--apply : Write missing suppressions instead of only reporting}
                            {--ids= : Comma-separated IVR import IDs to restore}
                            {--no-create-phones : Do not recreate phone numbers missing from the database}';

    protected $description = 'Restore active IVR unsubscriber suppressions from historical unsubscriber import files';

    public function handle(PhoneNormalizer $normalizer, NumberEligibilityService $eligibilityService): int
    {
        ini_set('memory_limit', '512M');

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $apply = (bool) $this->option('apply');
        $createMissingPhones = ! (bool) $this->option('no-create-phones');
        $importIds = $this->importIds();

        $imports = IvrImport::query()
            ->where('type', 'unsubscribers')
            ->whereIn('status', ['completed', 'completed_with_errors'])
            ->when($importIds !== [], fn ($query) => $query->whereIn('id', $importIds))
            ->orderBy('id')
            ->get();

        if ($imports->isEmpty()) {
            $this->warn('No completed IVR unsubscriber imports found.');

            return self::SUCCESS;
        }

        $entries = [];
        $missingFiles = [];
        $badRows = 0;
        $fileRows = 0;

        foreach ($imports as $import) {
            $path = storage_path('app/private/'.$import->storage_path);

            if (! is_file($path)) {
                $missingFiles[] = "{$import->id}: {$import->storage_path}";

                continue;
            }

            $file = new SplFileObject($path);
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl(',', '"', '\\');

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

                $fileRows++;
                $phone = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? '')) ?: null;

                try {
                    if ($phone === '') {
                        throw new \RuntimeException('Phone number is required.');
                    }

                    $normalized = $normalizer->normalize($phone);
                } catch (Throwable) {
                    $badRows++;

                    continue;
                }

                $key = $normalized['normalized'];
                $entries[$key] ??= [
                    'phone' => $phone,
                    'name' => $name,
                    'normalized' => $normalized,
                    'first_import' => [
                        'id' => $import->id,
                        'file' => $import->original_file_name,
                        'row' => $rowNumber,
                    ],
                    'import_ids' => [],
                    'import_count' => 0,
                ];

                if (! $entries[$key]['name'] && $name) {
                    $entries[$key]['name'] = $name;
                }

                $entries[$key]['import_ids'][$import->id] = true;
                $entries[$key]['import_count']++;
            }
        }

        $normalizedPhones = array_keys($entries);
        $phoneIdsByNormalized = [];

        foreach (array_chunk($normalizedPhones, 2000) as $chunk) {
            $phoneIdsByNormalized += ClientPhoneNumber::query()
                ->whereIn('normalized_phone', $chunk)
                ->pluck('id', 'normalized_phone')
                ->all();
        }

        $existingPhoneIds = array_values($phoneIdsByNormalized);
        $activeSuppressedIds = [];

        foreach (array_chunk($existingPhoneIds, 2000) as $chunk) {
            $activeSuppressedIds += ContactSuppression::query()
                ->whereIn('client_phone_number_id', $chunk)
                ->whereNull('released_at')
                ->where(fn ($query) => $query->whereNull('channel')->orWhere('channel', 'ivr'))
                ->pluck('client_phone_number_id')
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
        }

        $existingNeedingSuppression = 0;
        $missingPhoneCount = 0;

        foreach ($entries as $normalizedPhone => $entry) {
            $phoneId = $phoneIdsByNormalized[$normalizedPhone] ?? null;

            if (! $phoneId) {
                $missingPhoneCount++;

                continue;
            }

            if (! isset($activeSuppressedIds[$phoneId])) {
                $existingNeedingSuppression++;
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Imports scanned', $imports->count()],
                ['Files missing', count($missingFiles)],
                ['Rows scanned', number_format($fileRows)],
                ['Rows skipped as invalid phone', number_format($badRows)],
                ['Unique normalized phones', number_format(count($entries))],
                ['Phones already in DB', number_format(count($phoneIdsByNormalized))],
                ['Existing DB phones needing suppression', number_format($existingNeedingSuppression)],
                ['Phones missing from DB', number_format($missingPhoneCount)],
                ['Mode', $apply ? 'APPLY' : 'DRY RUN'],
                ['Recreate missing phones', $createMissingPhones ? 'yes' : 'no'],
            ],
        );

        if ($missingFiles !== []) {
            $this->warn('Missing files:');
            foreach ($missingFiles as $missingFile) {
                $this->line($missingFile);
            }
        }

        if (! $apply) {
            $this->info('Dry run only. Re-run with --apply to write changes.');

            return self::SUCCESS;
        }

        $createdPhones = 0;
        $createdSuppressions = 0;
        $refreshed = 0;

        foreach ($entries as $normalizedPhone => $entry) {
            $phoneId = $phoneIdsByNormalized[$normalizedPhone] ?? null;

            if ($phoneId && isset($activeSuppressedIds[$phoneId])) {
                continue;
            }

            DB::transaction(function () use (
                $normalizedPhone,
                $entry,
                $createMissingPhones,
                $eligibilityService,
                &$phoneIdsByNormalized,
                &$activeSuppressedIds,
                &$createdPhones,
                &$createdSuppressions,
                &$refreshed,
            ): void {
                $phoneId = $phoneIdsByNormalized[$normalizedPhone] ?? null;
                $phoneNumber = $phoneId ? ClientPhoneNumber::query()->find($phoneId) : null;

                if (! $phoneNumber && $createMissingPhones) {
                    $client = Client::create(['full_name' => $entry['name']]);

                    $phoneNumber = ClientPhoneNumber::create([
                        'client_id' => $client->id,
                        'raw_phone' => $entry['phone'],
                        'normalized_phone' => $entry['normalized']['normalized'],
                        'country_code' => $entry['normalized']['country_code'],
                        'national_number' => $entry['normalized']['national_number'],
                        'detected_country' => $entry['normalized']['detected_country'],
                        'is_uae' => $entry['normalized']['is_uae'],
                        'is_primary' => true,
                        'priority' => 1,
                    ]);

                    $phoneIdsByNormalized[$normalizedPhone] = $phoneNumber->id;
                    $createdPhones++;
                }

                if (! $phoneNumber) {
                    return;
                }

                $exists = ContactSuppression::query()
                    ->where('client_phone_number_id', $phoneNumber->id)
                    ->whereNull('released_at')
                    ->where(fn ($query) => $query->whereNull('channel')->orWhere('channel', 'ivr'))
                    ->exists();

                if (! $exists) {
                    ContactSuppression::create([
                        'client_phone_number_id' => $phoneNumber->id,
                        'channel' => 'ivr',
                        'reason' => 'unsubscribe',
                        'context' => [
                            'source' => 'historical_unsubscriber_restore',
                            'first_import' => $entry['first_import'],
                            'import_ids' => array_keys($entry['import_ids']),
                            'import_count' => $entry['import_count'],
                        ],
                        'suppressed_at' => now(),
                    ]);

                    $createdSuppressions++;
                    $activeSuppressedIds[$phoneNumber->id] = true;
                }

                if (! $phoneNumber->unsubscribed_at) {
                    $phoneNumber->forceFill(['unsubscribed_at' => now()])->save();
                }

                $eligibilityService->refresh($phoneNumber->refresh());
                $refreshed++;
            });

            if (($createdSuppressions + $createdPhones) > 0 && (($createdSuppressions + $createdPhones) % 1000) === 0) {
                $this->line('Restored '.number_format($createdSuppressions).' suppression(s), created '.number_format($createdPhones).' phone number(s)...');
            }
        }

        $this->info('Restore complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Phone numbers created', number_format($createdPhones)],
                ['Suppressions created', number_format($createdSuppressions)],
                ['Eligibility refreshed', number_format($refreshed)],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function importIds(): array
    {
        return collect(explode(',', (string) $this->option('ids')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => trim((string) $value) === '');
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
