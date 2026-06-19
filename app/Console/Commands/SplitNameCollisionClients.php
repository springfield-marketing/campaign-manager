<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\Identity\ClientSplitter;
use App\Support\RawContactImportEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SplitNameCollisionClients extends Command
{
    protected $signature = 'clients:split-name-collisions
                            {--threshold=10 : Minimum phone-number count to treat a client as a name collision}
                            {--stub-only : Only split clients whose name is a stub/placeholder (IMP-001) — leaves real-named high-volume clients (shared lines, institutions) untouched}
                            {--apply : Actually split (default is dry run)}';

    protected $description = 'Split a client that absorbed many unrelated phone numbers via a stub/placeholder name (e.g. "No Name", "Guest") back into one client per phone number';

    public function handle(ClientSplitter $splitter): int
    {
        $threshold = (int) $this->option('threshold');

        $candidates = DB::select('
            SELECT c.id, c.full_name, count(cpn.id) AS phone_count
            FROM clients c
            JOIN client_phone_numbers cpn ON cpn.client_id = c.id
            GROUP BY c.id, c.full_name
            HAVING count(cpn.id) >= ?
            ORDER BY phone_count DESC
        ', [$threshold]);

        // IMP-001: with --stub-only, restrict to placeholder-named clients so real high-volume
        // clients (banks, shared reception lines, genuine repeat contacts) are never split.
        // See docs/data-rules/imports.md.
        if ($this->option('stub-only')) {
            $candidates = array_values(array_filter(
                $candidates,
                fn ($c) => RawContactImportEnricher::isStubName((string) $c->full_name),
            ));
        }

        if ($candidates === []) {
            $this->info("No clients found with {$threshold}+ phone numbers.");

            return self::SUCCESS;
        }

        $this->table(
            ['Client ID', 'Name', 'Phone Count'],
            array_map(fn ($c) => [$c->id, $c->full_name, $c->phone_count], $candidates),
        );

        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to actually split.');
            $this->warn(
                'On split: the ANCHOR number (the one whose source produced this client\'s name) '.
                'stays on the original, keeping its name, history and client-level data (emails, '.
                'tags, ownerships). Every OTHER number moves to its own client, named from that '.
                'number\'s own source provenance (or blank if none). All campaign history follows '.
                'each number. A reversible snapshot is saved first.'
            );

            return self::SUCCESS;
        }

        $totalMoved = 0;
        $totalDeletedAsPlaceholder = 0;

        foreach ($candidates as $candidate) {
            $client = Client::find($candidate->id);
            if (! $client) {
                continue;
            }

            $result = $splitter->split($client);
            $totalMoved += $result['moved'];
            $totalDeletedAsPlaceholder += $result['deleted'];

            $this->line("Split client #{$candidate->id} (\"{$client->full_name}\"): kept anchor, moved {$result['moved']} number(s) out.");
        }

        $this->info("Done. {$totalMoved} number(s) moved to their own client, {$totalDeletedAsPlaceholder} legacy placeholder number(s) deleted.");

        return self::SUCCESS;
    }
}
