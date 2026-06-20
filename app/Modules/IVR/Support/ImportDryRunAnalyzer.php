<?php

namespace App\Modules\IVR\Support;

use App\Models\ClientPhoneNumber;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use Throwable;

/**
 * Pre-call dry run for a raw-contacts CSV: counts how many numbers are duplicates, already in
 * the database, already on the Do-Not-Call list, or resting in cooldown (recently called) —
 * BEFORE committing the import — so an operator can see how many would be wasted calls.
 *
 * Read-only. Capped at MAX_ROWS so the preview stays a fast synchronous request on very large
 * files; the result reports `sampled` when the cap is hit.
 */
class ImportDryRunAnalyzer
{
    use CsvRowTrait;

    private const MAX_ROWS = 20000;

    private const DB_CHUNK = 5000;

    public function __construct(
        private readonly RawImportColumnMapper $mapper,
        private readonly PhoneNormalizer $phoneNormalizer,
    ) {}

    /**
     * @return array{
     *   ok:bool, missing_columns:array<int,string>, analyzed:int, sampled:bool,
     *   with_phone:int, name_only:int, file_duplicates:int, distinct_phones:int,
     *   existing:int, suppressed:int, in_cooldown:int, fresh:int
     * }
     */
    public function analyze(string $absolutePath): array
    {
        $file = new SplFileObject($absolutePath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '\\');

        $header = $this->readHeader($file);
        $mapping = $this->mapper->map($header);

        if ($mapping['missing'] !== []) {
            return $this->emptyResult($mapping['missing']);
        }

        $phoneIndex = $mapping['mapped']['phone'] ?? null;

        $analyzed = 0;
        $withPhone = 0;
        $nameOnly = 0;
        $sampled = false;
        $occurrences = []; // normalized_phone => count within the analyzed rows

        while (! $file->eof()) {
            if ($analyzed >= self::MAX_ROWS) {
                $sampled = true;
                break;
            }

            $row = $file->fgetcsv();
            if (! is_array($row) || ($row === [null] && $file->eof())) {
                break;
            }
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $analyzed++;

            $rawPhone = ($phoneIndex !== null && isset($row[$phoneIndex])) ? trim((string) $row[$phoneIndex]) : '';
            if ($rawPhone === '') {
                $nameOnly++;

                continue;
            }

            try {
                $normalized = $this->phoneNormalizer->normalize($rawPhone)['normalized'] ?? null;
            } catch (Throwable) {
                $normalized = null;
            }

            if (! $normalized) {
                $nameOnly++;

                continue;
            }

            $withPhone++;
            $occurrences[$normalized] = ($occurrences[$normalized] ?? 0) + 1;
        }

        $distinct = array_keys($occurrences);
        $fileDuplicates = $withPhone - count($distinct); // extra occurrences of a repeated phone

        // Set-based lookups on the distinct phones in the file.
        $existingIds = [];
        foreach (array_chunk($distinct, self::DB_CHUNK) as $chunk) {
            foreach (ClientPhoneNumber::whereIn('normalized_phone', $chunk)->get(['id', 'normalized_phone']) as $phone) {
                $existingIds[$phone->normalized_phone] = $phone->id;
            }
        }

        $ids = array_values($existingIds);
        $suppressed = 0;
        $inCooldown = 0;

        foreach (array_chunk($ids, self::DB_CHUNK) as $chunk) {
            $suppressed += DB::table('contact_suppressions')
                ->whereIn('client_phone_number_id', $chunk)
                ->whereNull('released_at')
                ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'ivr'))
                ->distinct()
                ->count('client_phone_number_id');

            $inCooldown += DB::table('ivr_phone_profiles')
                ->whereIn('client_phone_number_id', $chunk)
                ->whereNotNull('cooldown_until')
                ->where('cooldown_until', '>', now())
                ->count();
        }

        return [
            'ok' => true,
            'missing_columns' => [],
            'analyzed' => $analyzed,
            'sampled' => $sampled,
            'with_phone' => $withPhone,
            'name_only' => $nameOnly,
            'file_duplicates' => $fileDuplicates,
            'distinct_phones' => count($distinct),
            'existing' => count($existingIds),
            'suppressed' => $suppressed,
            'in_cooldown' => $inCooldown,
            'fresh' => count($distinct) - count($existingIds),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function readHeader(SplFileObject $file): array
    {
        while (! $file->eof()) {
            $header = $file->fgetcsv();

            if (! is_array($header) || ($header === [null] && $file->eof())) {
                break;
            }

            if (! $this->rowIsEmpty($header)) {
                $header = array_map(fn ($value) => (string) $value, $header);
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

                return $header;
            }
        }

        throw new \RuntimeException('Import file is empty.');
    }

    /**
     * @param  array<int, string>  $missing
     */
    private function emptyResult(array $missing): array
    {
        return [
            'ok' => false,
            'missing_columns' => $missing,
            'analyzed' => 0,
            'sampled' => false,
            'with_phone' => 0,
            'name_only' => 0,
            'file_duplicates' => 0,
            'distinct_phones' => 0,
            'existing' => 0,
            'suppressed' => 0,
            'in_cooldown' => 0,
            'fresh' => 0,
        ];
    }
}
