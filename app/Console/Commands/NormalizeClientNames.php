<?php

namespace App\Console\Commands;

use App\Support\NameNormalizer;
use App\Support\RawContactImportEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeClientNames extends Command
{
    protected $signature = 'clients:normalize-names
                            {--dry-run : Preview changes without saving}
                            {--chunk=500 : Records per batch}';

    protected $description = 'Normalise client full_name values to Title Case and backfill alternate_names from import history';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $dryRun    = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be saved.');
        }

        $this->info('Step 1: Normalising full_name values…');
        $normalised = $this->normaliseFullNames($dryRun, $chunkSize);
        $label = $dryRun ? 'would be updated' : 'updated';
        $this->line("  → {$normalised} name(s) {$label}.");

        $this->newLine();
        $this->info('Step 2: Backfilling alternate_names from import history…');
        $backfilled = $this->backfillAlternateNames($dryRun, $chunkSize);
        $label = $dryRun ? 'would receive alternate names' : 'received alternate names';
        $this->line("  → {$backfilled} client(s) {$label}.");

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function normaliseFullNames(bool $dryRun, int $chunkSize): int
    {
        $changed = 0;

        DB::table('clients')
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($dryRun, &$changed): void {
                $updates = [];

                foreach ($rows as $row) {
                    $normalised = NameNormalizer::normalize($row->full_name);

                    if ($normalised === '' || $normalised === $row->full_name) {
                        continue;
                    }

                    if ($this->output->isVerbose()) {
                        $this->line(sprintf('  [%d] %s → %s', $row->id, $row->full_name, $normalised));
                    }

                    $updates[$row->id] = $normalised;
                }

                if (empty($updates) || $dryRun) {
                    $changed += count($updates);
                    return;
                }

                $cases    = implode(' ', array_map(fn () => 'WHEN ? THEN ?', $updates));
                $bindings = [];
                foreach ($updates as $id => $value) {
                    $bindings[] = $id;
                    $bindings[] = $value;
                }
                $ids = implode(',', array_fill(0, count($updates), '?'));

                DB::statement(
                    "UPDATE clients SET full_name = CASE id {$cases} END, updated_at = NOW() WHERE id IN ({$ids})",
                    [...$bindings, ...array_keys($updates)],
                );

                $changed += count($updates);
            });

        return $changed;
    }

    private function backfillAlternateNames(bool $dryRun, int $chunkSize): int
    {
        $count = 0;

        // Collect all client IDs that appear in import history with a raw_name
        $clientIds = DB::table('client_sources')
            ->whereNotNull('client_id')
            ->whereRaw("metadata->>'raw_name' IS NOT NULL")
            ->whereRaw("trim(metadata->>'raw_name') != ''")
            ->distinct()
            ->orderBy('client_id')
            ->pluck('client_id')
            ->all();

        if (empty($clientIds)) {
            return 0;
        }

        $this->line('  Processing ' . count($clientIds) . ' clients with import history…');

        foreach (array_chunk($clientIds, $chunkSize) as $chunk) {
            // One row per client: distinct raw_names aggregated into a JSON array in SQL
            // This avoids loading potentially millions of source rows into PHP memory.
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $rawNameRows  = DB::select(
                "SELECT client_id, json_agg(raw_name) AS raw_names
                 FROM (
                     SELECT DISTINCT client_id, trim(metadata->>'raw_name') AS raw_name
                     FROM client_sources
                     WHERE client_id IN ({$placeholders})
                       AND metadata->>'raw_name' IS NOT NULL
                       AND trim(metadata->>'raw_name') != ''
                 ) sub
                 GROUP BY client_id",
                $chunk,
            );

            $rawNamesByClient = [];
            foreach ($rawNameRows as $row) {
                $rawNamesByClient[$row->client_id] = json_decode($row->raw_names, true) ?? [];
            }

            $clients = DB::table('clients')
                ->whereIn('id', $chunk)
                ->get(['id', 'full_name', 'alternate_names'])
                ->keyBy('id');

            foreach ($chunk as $clientId) {
                $client = $clients->get($clientId);
                if (! $client) {
                    continue;
                }

                // Use the already-normalised full_name as the baseline (Step 1 ran first)
                $storedNormalised = NameNormalizer::normalize((string) ($client->full_name ?? ''));
                if ($storedNormalised === '') {
                    continue;
                }

                $existing = json_decode((string) ($client->alternate_names ?? 'null'), true) ?? [];
                $rawNames = $rawNamesByClient[$clientId] ?? [];
                $toAdd    = [];

                foreach ($rawNames as $rawName) {
                    $rawName = trim((string) $rawName);

                    if ($rawName === '' || RawContactImportEnricher::isStubName($rawName)) {
                        continue;
                    }

                    $normalised = NameNormalizer::normalize($rawName);

                    if ($normalised === '' || $normalised === $storedNormalised) {
                        continue;
                    }

                    if (in_array($normalised, $existing, true) || in_array($normalised, $toAdd, true)) {
                        continue;
                    }

                    $toAdd[] = $normalised;
                }

                if (empty($toAdd)) {
                    continue;
                }

                $merged = array_values(array_unique(array_merge($existing, $toAdd)));

                if ($this->output->isVerbose()) {
                    $this->line(sprintf(
                        '  [%d] %s ← %s',
                        $clientId,
                        $client->full_name,
                        implode(', ', $toAdd),
                    ));
                }

                if (! $dryRun) {
                    DB::table('clients')->where('id', $clientId)->update([
                        'alternate_names' => json_encode($merged),
                        'updated_at'      => now()->toDateTimeString(),
                    ]);
                }

                $count++;
            }
        }

        return $count;
    }
}
